import 'bootstrap';
import Chart from 'chart.js/auto';

const cssVar = (name, fallback) =>
    getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;

const accent = () => cssVar('--saha-accent', '#0d9488');
const muted = () => cssVar('--bs-secondary-color', '#8b9bb4');
const grid = () => cssVar('--saha-border', '#1f2a3d');
const surface = () => cssVar('--saha-surface', '#121a2b');
const bodyColor = () => cssVar('--bs-body-color', '#e8eef7');

const palette = {
    green: '#3dd68c',
    orange: '#f08c2b',
    teal: '#2dd4bf',
    yellow: '#f5c518',
    blue: '#4cc3f2',
    violet: '#8b7cf7',
    red: '#f87171',
};

const resolveColor = (name) => palette[name] || name || accent();

const normalizeUnit = (unit) => {
    if (!unit || unit === 'count') {
        return 'count';
    }

    if (unit === '%' || unit === 'percent') {
        return 'percent';
    }

    return unit;
};

const colorWithAlpha = (color, alpha) => {
    const hex = color.replace('#', '');

    if (hex.length === 3 || hex.length === 6) {
        const full = hex.length === 3 ? hex.split('').map((char) => char + char).join('') : hex;
        const red = Number.parseInt(full.slice(0, 2), 16);
        const green = Number.parseInt(full.slice(2, 4), 16);
        const blue = Number.parseInt(full.slice(4, 6), 16);

        return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
    }

    return color;
};

const formatNumber = (value) => {
    const amount = Number(value);

    if (!Number.isFinite(amount)) {
        return '';
    }

    const abs = Math.abs(amount);

    if (abs >= 1_000_000) {
        return `${(amount / 1_000_000).toFixed(abs >= 10_000_000 ? 0 : 1)}M`;
    }

    if (abs >= 1_000) {
        return `${(amount / 1_000).toFixed(abs >= 10_000 ? 0 : 1)}k`;
    }

    if (Number.isInteger(amount)) {
        return String(amount);
    }

    return amount.toFixed(abs >= 10 ? 1 : 2);
};

const formatTick = (value, unit) => {
    const amount = Number(value);

    if (!Number.isFinite(amount)) {
        return '';
    }

    if (unit === 'bytes') {
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let size = amount;
        let index = 0;

        while (size >= 1024 && index < units.length - 1) {
            size /= 1024;
            index += 1;
        }

        return `${size.toFixed(size >= 10 ? 0 : 1)} ${units[index]}`;
    }

    if (unit === 'seconds') {
        return `${Math.round(amount / 3600)} h`;
    }

    if (unit === 'percent') {
        return `${amount >= 10 ? amount.toFixed(0) : amount.toFixed(1)}%`;
    }

    if (unit === 'ms') {
        if (amount >= 1000) {
            return `${(amount / 1000).toFixed(amount >= 10_000 ? 0 : 1)} s`;
        }

        return `${Math.round(amount)} ms`;
    }

    return formatNumber(amount);
};

const formatTimeLabel = (label) => {
    const date = new Date(label);

    if (Number.isNaN(date.getTime())) {
        return label;
    }

    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const formatTooltipTitle = (label) => {
    const date = new Date(label);

    if (Number.isNaN(date.getTime())) {
        return label;
    }

    return date.toLocaleString([], {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const seriesFrom = (config) => {
    if (Array.isArray(config.series) && config.series.length > 0) {
        return config.series;
    }

    return [
        {
            label: config.label || 'Metric',
            values: config.values || [],
            color: config.color,
        },
    ];
};

const lineFill = (color) => (context) => {
    const { ctx, chartArea } = context.chart;

    if (!chartArea) {
        return colorWithAlpha(color, 0.12);
    }

    const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
    gradient.addColorStop(0, colorWithAlpha(color, 0.28));
    gradient.addColorStop(1, colorWithAlpha(color, 0.02));

    return gradient;
};

Chart.defaults.font.family = "'IBM Plex Sans', 'Segoe UI', sans-serif";
Chart.defaults.font.size = 11;
Chart.defaults.animation = false;

document.querySelectorAll('[data-metric-chart]').forEach((canvas) => {
    const config = JSON.parse(canvas.getAttribute('data-config') || '{}');
    const unit = normalizeUnit(config.unit);
    const type = config.type === 'bar' ? 'bar' : 'line';
    const series = seriesFrom(config);
    const filled = config.fill !== false && type === 'line' && series.length === 1;
    const showLegend = config.legend === true || series.length > 1;
    const peak = series
        .flatMap((item) => item.values || [])
        .reduce((max, value) => (Number.isFinite(Number(value)) && Number(value) > max ? Number(value) : max), 0);
    const usageScale = unit === 'percent' && peak >= 20;

    const chart = new Chart(canvas, {
        type,
        data: {
            labels: config.labels || [],
            datasets: series.map((item, index) => {
                const color = resolveColor(item.color) || [accent(), palette.yellow, palette.violet][index] || accent();

                if (type === 'bar') {
                    return {
                        label: item.label || config.label || 'Metric',
                        data: item.values || [],
                        backgroundColor: color,
                        hoverBackgroundColor: color,
                        borderWidth: 0,
                        borderRadius: 0,
                        barPercentage: 0.9,
                        categoryPercentage: 0.9,
                        maxBarThickness: 10,
                    };
                }

                return {
                    label: item.label || config.label || 'Metric',
                    data: item.values || [],
                    borderColor: color,
                    backgroundColor: filled ? lineFill(color) : 'transparent',
                    fill: filled ? 'origin' : false,
                    pointRadius: 0,
                    pointHoverRadius: 3,
                    pointHoverBorderWidth: 2,
                    pointHoverBackgroundColor: surface(),
                    pointHoverBorderColor: color,
                    borderWidth: 1.75,
                    tension: 0.2,
                    cubicInterpolationMode: 'monotone',
                    spanGaps: true,
                };
            }),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            resizeDelay: 50,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    display: showLegend,
                    position: 'top',
                    align: 'end',
                    labels: {
                        color: muted(),
                        boxWidth: 8,
                        boxHeight: 8,
                        padding: 12,
                        usePointStyle: true,
                        pointStyle: type === 'bar' ? 'rect' : 'line',
                    },
                },
                tooltip: {
                    backgroundColor: surface(),
                    titleColor: bodyColor(),
                    bodyColor: bodyColor(),
                    borderColor: grid(),
                    borderWidth: 1,
                    cornerRadius: 8,
                    padding: 10,
                    displayColors: showLegend,
                    callbacks: {
                        title: (items) => formatTooltipTitle(items[0]?.label ?? ''),
                        label: (item) => `${item.dataset.label}: ${formatTick(item.parsed.y, unit)}`,
                    },
                },
            },
            scales: {
                x: {
                    stacked: false,
                    border: { display: false },
                    ticks: {
                        color: muted(),
                        maxTicksLimit: 8,
                        maxRotation: 0,
                        autoSkip: true,
                        callback(value) {
                            return formatTimeLabel(this.getLabelForValue(value));
                        },
                    },
                    grid: { display: false },
                },
                y: {
                    border: { display: false },
                    beginAtZero: true,
                    grace: '6%',
                    suggestedMax: usageScale ? 100 : undefined,
                    ticks: {
                        color: muted(),
                        maxTicksLimit: 5,
                        padding: 8,
                        callback: (value) => formatTick(value, unit),
                    },
                    grid: {
                        color: colorWithAlpha(grid(), 0.55),
                        drawTicks: false,
                    },
                },
            },
        },
    });

    canvas._sahaChart = chart;
});

const formatLivePercent = (value) => {
    if (value === null || value === undefined || !Number.isFinite(Number(value))) {
        return '—';
    }

    return `${Number(value).toFixed(1)}<span class="stat-card-hint">%</span>`;
};

const applyStatusBadge = (element, label, variant) => {
    if (!element) {
        return;
    }

    element.textContent = label;
    element.className = `badge text-bg-${variant}`;
};

const refreshHostLive = async (root) => {
    const url = new URL(root.getAttribute('data-live-host'), window.location.origin);
    url.searchParams.set('range', root.getAttribute('data-live-range') || '6h');

    const response = await fetch(url.toString(), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });

    if (!response.ok) {
        return;
    }

    const payload = await response.json();

    root.querySelectorAll('[data-live-metric]').forEach((card) => {
        const key = card.getAttribute('data-live-metric');
        const target = card.querySelector('[data-live-value]');

        if (target) {
            target.innerHTML = formatLivePercent(payload.usage?.[key]);
        }
    });

    applyStatusBadge(
        root.querySelector('[data-live-status]'),
        payload.status_label,
        payload.status_variant,
    );

    Object.entries(payload.charts || {}).forEach(([key, chartConfig]) => {
        const canvas = root.querySelector(`[data-metric-chart][data-chart-key="${key}"]`);
        const chart = canvas?._sahaChart;

        if (!chart || !chartConfig) {
            return;
        }

        chart.data.labels = chartConfig.labels || [];
        chart.data.datasets[0].data = chartConfig.values || [];
        chart.update('none');
    });
};

const refreshHostsIndex = async (root) => {
    const ids = [...root.querySelectorAll('[data-host-id]')]
        .map((row) => row.getAttribute('data-host-id'))
        .filter(Boolean)
        .join(',');

    if (ids === '') {
        return;
    }

    const url = new URL(root.getAttribute('data-live-hosts'), window.location.origin);
    url.searchParams.set('ids', ids);

    const response = await fetch(url.toString(), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });

    if (!response.ok) {
        return;
    }

    const payload = await response.json();

    Object.entries(payload.hosts || {}).forEach(([id, host]) => {
        const row = root.querySelector(`[data-host-id="${id}"]`);

        if (!row) {
            return;
        }

        ['cpu', 'memory', 'disk'].forEach((key) => {
            const cell = row.querySelector(`[data-host-col="${key}"]`);

            if (cell) {
                cell.textContent = host[key] === null || host[key] === undefined
                    ? '—'
                    : `${Number(host[key]).toFixed(1)}%`;
            }
        });

        const seen = row.querySelector('[data-host-col="last-seen"]');

        if (seen && host.last_seen_at) {
            seen.textContent = host.last_seen_at;
        }

        applyStatusBadge(
            row.querySelector('[data-host-col="status"] .badge'),
            host.status_label,
            host.status_variant,
        );
    });
};

const startLivePolling = (element, tick) => {
    const run = async () => {
        if (document.hidden) {
            return;
        }

        try {
            await tick(element);
        } catch (error) {
            console.warn('Live metrics refresh failed', error);
        }
    };

    run();
    window.setInterval(run, 5000);
};

document.querySelectorAll('[data-live-host]').forEach((root) => startLivePolling(root, refreshHostLive));
document.querySelectorAll('[data-live-hosts]').forEach((root) => startLivePolling(root, refreshHostsIndex));
