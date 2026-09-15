const LAND = [
    [[-168, 71], [-141, 70], [-127, 71], [-105, 69], [-88, 66], [-82, 62], [-70, 58], [-56, 51], [-64, 46], [-67, 45], [-79, 43], [-80, 32], [-97, 26], [-106, 22], [-111, 31], [-124, 38], [-124, 48], [-153, 58], [-166, 64], [-168, 71]],
    [[-117, 32], [-110, 24], [-105, 20], [-97, 16], [-90, 14], [-84, 9], [-77, 8], [-81, 22], [-90, 22], [-97, 26], [-106, 22], [-117, 32]],
    [[-81, 12], [-77, 8], [-79, 1], [-80, -5], [-77, -12], [-70, -18], [-71, -41], [-73, -53], [-68, -55], [-65, -42], [-58, -38], [-53, -33], [-48, -25], [-43, -22], [-35, -8], [-50, 0], [-52, 5], [-60, 8], [-70, 12], [-77, 12], [-81, 12]],
    [[-10, 36], [-5, 36], [-5, 43], [3, 43], [8, 44], [10, 42], [16, 41], [18, 40], [23, 37], [29, 41], [28, 45], [24, 45], [13, 46], [5, 49], [2, 51], [-5, 48], [-10, 44], [-9, 39], [-10, 36]],
    [[-10, 52], [-6, 58], [-5, 59], [5, 62], [10, 63], [12, 66], [24, 71], [31, 70], [40, 68], [44, 66], [40, 60], [30, 60], [24, 57], [14, 55], [8, 54], [1, 53], [-5, 50], [-10, 52]],
    [[-17, 21], [-16, 16], [-17, 12], [-14, 10], [-5, 5], [0, 6], [8, 4], [14, 5], [18, 5], [18, -5], [12, -6], [13, -12], [12, -17], [19, -34], [18, -35], [25, -34], [32, -29], [29, -24], [34, -20], [40, -11], [42, -3], [51, 12], [43, 12], [32, 31], [18, 31], [10, 37], [-5, 36], [-17, 21]],
    [[32, 46], [29, 41], [36, 36], [36, 31], [44, 33], [48, 30], [51, 26], [59, 25], [66, 25], [73, 21], [77, 8], [80, 6], [87, 22], [92, 22], [94, 18], [98, 9], [104, 2], [109, 13], [109, 20], [119, 25], [122, 31], [129, 35], [141, 37], [146, 43], [142, 46], [135, 44], [129, 43], [116, 40], [109, 34], [98, 28], [88, 28], [80, 31], [74, 37], [67, 37], [61, 45], [50, 42], [40, 45], [40, 48], [32, 46]],
    [[73, 8], [80, 6], [80, 16], [77, 19], [72, 21], [70, 19], [73, 8]],
    [[113, 22], [121, 22], [122, 14], [126, 7], [125, 3], [118, 2], [104, 1], [100, 3], [100, 7], [104, 11], [109, 13], [113, 22]],
    [[129, -14], [136, -12], [142, -11], [147, -19], [153, -25], [153, -32], [150, -38], [145, -38], [139, -35], [135, -34], [131, -32], [115, -34], [114, -22], [122, -16], [129, -14]],
    [[166, -34], [179, -37], [178, -46], [167, -47], [166, -41], [172, -34], [166, -34]],
    [[-45, 83], [-60, 76], [-45, 60], [-22, 70], [-10, 84], [-45, 83]],
];

const toRad = (value) => (value * Math.PI) / 180;

const project = (lat, lng, rotY, rotX, radius, cx, cy) => {
    const phi = toRad(lat);
    const lambda = toRad(lng) + rotY;
    const cosPhi = Math.cos(phi);
    let x = cosPhi * Math.sin(lambda);
    let y = Math.sin(phi);
    let z = cosPhi * Math.cos(lambda);
    const cosX = Math.cos(rotX);
    const sinX = Math.sin(rotX);
    const y2 = y * cosX - z * sinX;
    const z2 = y * sinX + z * cosX;

    return {
        x: cx + x * radius,
        y: cy - y2 * radius,
        z: z2,
        visible: z2 > -0.02,
    };
};

const greatCircle = (from, to, steps = 28) => {
    const a = {
        x: Math.cos(toRad(from.lat)) * Math.cos(toRad(from.lng)),
        y: Math.sin(toRad(from.lat)),
        z: Math.cos(toRad(from.lat)) * Math.sin(toRad(from.lng)),
    };
    const b = {
        x: Math.cos(toRad(to.lat)) * Math.cos(toRad(to.lng)),
        y: Math.sin(toRad(to.lat)),
        z: Math.cos(toRad(to.lat)) * Math.sin(toRad(to.lng)),
    };
    const dot = Math.max(-1, Math.min(1, a.x * b.x + a.y * b.y + a.z * b.z));
    const omega = Math.acos(dot);
    const points = [];

    if (omega < 0.001) {
        return [from, to];
    }

    for (let index = 0; index <= steps; index += 1) {
        const t = index / steps;
        const sinOmega = Math.sin(omega);
        const w0 = Math.sin((1 - t) * omega) / sinOmega;
        const w1 = Math.sin(t * omega) / sinOmega;
        const x = a.x * w0 + b.x * w1;
        const y = a.y * w0 + b.y * w1;
        const z = a.z * w0 + b.z * w1;
        points.push({
            lat: Math.asin(Math.max(-1, Math.min(1, y))) * (180 / Math.PI),
            lng: Math.atan2(z, x) * (180 / Math.PI),
        });
    }

    return points;
};

const drawLand = (ctx, rotY, rotX, radius, cx, cy, color) => {
    ctx.strokeStyle = color;
    ctx.lineWidth = 1;
    ctx.lineJoin = 'round';

    LAND.forEach((ring) => {
        let started = false;
        ctx.beginPath();
        ring.forEach(([lng, lat]) => {
            const point = project(lat, lng, rotY, rotX, radius, cx, cy);

            if (! point.visible) {
                started = false;
                return;
            }

            if (! started) {
                ctx.moveTo(point.x, point.y);
                started = true;
            } else {
                ctx.lineTo(point.x, point.y);
            }
        });
        ctx.stroke();
    });
};

const drawGrid = (ctx, rotY, rotX, radius, cx, cy, color) => {
    ctx.strokeStyle = color;
    ctx.lineWidth = 0.6;

    for (let lat = -60; lat <= 60; lat += 30) {
        let started = false;
        ctx.beginPath();
        for (let lng = -180; lng <= 180; lng += 6) {
            const point = project(lat, lng, rotY, rotX, radius, cx, cy);
            if (! point.visible) {
                started = false;
                continue;
            }
            if (! started) {
                ctx.moveTo(point.x, point.y);
                started = true;
            } else {
                ctx.lineTo(point.x, point.y);
            }
        }
        ctx.stroke();
    }

    for (let lng = -180; lng < 180; lng += 30) {
        let started = false;
        ctx.beginPath();
        for (let lat = -80; lat <= 80; lat += 4) {
            const point = project(lat, lng, rotY, rotX, radius, cx, cy);
            if (! point.visible) {
                started = false;
                continue;
            }
            if (! started) {
                ctx.moveTo(point.x, point.y);
                started = true;
            } else {
                ctx.lineTo(point.x, point.y);
            }
        }
        ctx.stroke();
    }
};

const drawArc = (ctx, from, to, rotY, rotX, radius, cx, cy, color, phase) => {
    const samples = greatCircle(from, to);
    ctx.strokeStyle = color;
    ctx.lineWidth = 1.15;
    ctx.setLineDash([5, 7]);
    ctx.lineDashOffset = -phase * 18;
    let started = false;
    ctx.beginPath();
    samples.forEach((sample) => {
        const point = project(sample.lat, sample.lng, rotY, rotX, radius, cx, cy);
        if (! point.visible) {
            started = false;
            return;
        }
        if (! started) {
            ctx.moveTo(point.x, point.y);
            started = true;
        } else {
            ctx.lineTo(point.x, point.y);
        }
    });
    ctx.stroke();
    ctx.setLineDash([]);
};

const drawPoint = (ctx, point, radius, color, glow) => {
    if (! point.visible) {
        return;
    }

    ctx.save();
    ctx.beginPath();
    ctx.fillStyle = glow;
    ctx.arc(point.x, point.y, radius * 2.4, 0, Math.PI * 2);
    ctx.fill();
    ctx.beginPath();
    ctx.fillStyle = color;
    ctx.arc(point.x, point.y, radius, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
};

const maxRequests = (points) => points.reduce((max, point) => Math.max(max, point.requests || 0), 1);

export const mountUsageGlobe = (canvas, config) => {
    if (! canvas) {
        return;
    }

    if (canvas._sahaGlobe?.frame) {
        window.cancelAnimationFrame(canvas._sahaGlobe.frame);
    }

    const state = {
        rotY: 0.35,
        rotX: -0.28,
        dragging: false,
        lastX: 0,
        lastY: 0,
        auto: true,
        phase: 0,
        config: config || { origin: null, points: [] },
        frame: 0,
    };

    const paint = () => {
        const ctx = canvas.getContext('2d');
        const width = canvas.clientWidth || 640;
        const height = canvas.clientHeight || 420;
        const ratio = window.devicePixelRatio || 1;
        canvas.width = Math.round(width * ratio);
        canvas.height = Math.round(height * ratio);
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.clearRect(0, 0, width, height);

        const cx = width / 2;
        const cy = height / 2 + 6;
        const radius = Math.min(width, height) * 0.42;

        const ocean = ctx.createRadialGradient(cx - radius * 0.3, cy - radius * 0.35, radius * 0.2, cx, cy, radius * 1.15);
        ocean.addColorStop(0, '#1b3b55');
        ocean.addColorStop(0.55, '#0d2136');
        ocean.addColorStop(1, '#07111d');
        ctx.beginPath();
        ctx.arc(cx, cy, radius, 0, Math.PI * 2);
        ctx.fillStyle = ocean;
        ctx.fill();

        ctx.beginPath();
        ctx.arc(cx, cy, radius, 0, Math.PI * 2);
        ctx.strokeStyle = 'rgba(45, 212, 191, 0.18)';
        ctx.lineWidth = 2;
        ctx.stroke();

        const halo = ctx.createRadialGradient(cx, cy, radius * 0.92, cx, cy, radius * 1.18);
        halo.addColorStop(0, 'rgba(45, 212, 191, 0)');
        halo.addColorStop(1, 'rgba(45, 212, 191, 0.16)');
        ctx.beginPath();
        ctx.arc(cx, cy, radius * 1.16, 0, Math.PI * 2);
        ctx.fillStyle = halo;
        ctx.fill();

        ctx.save();
        ctx.beginPath();
        ctx.arc(cx, cy, radius, 0, Math.PI * 2);
        ctx.clip();
        drawGrid(ctx, state.rotY, state.rotX, radius, cx, cy, 'rgba(148, 178, 204, 0.16)');
        drawLand(ctx, state.rotY, state.rotX, radius, cx, cy, 'rgba(186, 214, 232, 0.62)');

        const origin = state.config.origin;
        const points = state.config.points || [];
        const peak = maxRequests(points);

        if (origin) {
            points.slice(0, 40).forEach((item) => {
                drawArc(ctx, item, origin, state.rotY, state.rotX, radius, cx, cy, 'rgba(45, 212, 191, 0.45)', state.phase);
            });
        }

        points.forEach((item) => {
            const projected = project(item.lat, item.lng, state.rotY, state.rotX, radius, cx, cy);
            const size = 2.2 + (3.6 * (item.requests || 1)) / peak;
            drawPoint(ctx, projected, size, '#5eead4', 'rgba(45, 212, 191, 0.22)');
        });

        if (origin) {
            const projected = project(origin.lat, origin.lng, state.rotY, state.rotX, radius, cx, cy);
            drawPoint(ctx, projected, 5.2 + Math.sin(state.phase * Math.PI * 2) * 0.7, '#f5c518', 'rgba(245, 197, 24, 0.28)');
        }

        ctx.restore();

        const sheen = ctx.createLinearGradient(cx - radius, cy - radius, cx + radius, cy + radius);
        sheen.addColorStop(0, 'rgba(255, 255, 255, 0.08)');
        sheen.addColorStop(0.45, 'rgba(255, 255, 255, 0)');
        sheen.addColorStop(1, 'rgba(0, 0, 0, 0.18)');
        ctx.beginPath();
        ctx.arc(cx, cy, radius, 0, Math.PI * 2);
        ctx.fillStyle = sheen;
        ctx.fill();
    };

    const tick = () => {
        if (! document.hidden) {
            if (state.auto && ! state.dragging) {
                state.rotY += 0.0032;
            }
            state.phase = (state.phase + 0.008) % 1;
            paint();
        }

        state.frame = window.requestAnimationFrame(tick);
    };

    const onDown = (event) => {
        state.dragging = true;
        state.auto = false;
        state.lastX = event.clientX;
        state.lastY = event.clientY;
    };

    const onMove = (event) => {
        if (! state.dragging) {
            return;
        }

        state.rotY += (event.clientX - state.lastX) * 0.008;
        state.rotX = Math.max(-0.9, Math.min(0.9, state.rotX + (event.clientY - state.lastY) * 0.006));
        state.lastX = event.clientX;
        state.lastY = event.clientY;
    };

    const onUp = () => {
        state.dragging = false;
    };

    canvas.addEventListener('pointerdown', onDown);
    window.addEventListener('pointermove', onMove);
    window.addEventListener('pointerup', onUp);

    canvas._sahaGlobe = state;
    tick();
};

export const updateUsageGlobe = (canvas, config) => {
    if (! canvas?._sahaGlobe || ! config) {
        if (canvas && ! canvas._sahaGlobe) {
            mountUsageGlobe(canvas, config);
        }

        return;
    }

    canvas._sahaGlobe.config = config;
};
