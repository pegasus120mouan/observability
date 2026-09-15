@php
    $nav = [
        ['label' => 'Overview', 'route' => 'overview', 'icon' => 'bi-grid-1x2', 'enabled' => true],
    ];
@endphp

<aside class="app-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar">
    <div class="sidebar-brand">
        <span class="brand-mark">{{ mb_substr(config('platform.short_name'), 0, 1) }}</span>
        <div>
            <div class="brand-name">{{ config('platform.short_name') }}</div>
            <div class="brand-subtitle">Observability</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="{{ route('overview') }}" class="sidebar-link {{ request()->routeIs('overview') ? 'active' : '' }}">
            <i class="bi bi-grid-1x2"></i> Overview
        </a>

        <div class="sidebar-label">Monitoring</div>
        @can('viewAny', App\Models\Host::class)
            <a href="{{ route('hosts.index') }}" class="sidebar-link {{ request()->routeIs('hosts.*') ? 'active' : '' }}">
                <i class="bi bi-hdd-network"></i> Hosts
            </a>
        @else
            <span class="sidebar-link disabled"><i class="bi bi-hdd-network"></i> Hosts</span>
        @endcan
        @can('viewAny', App\Models\Agent::class)
            <a href="{{ route('agents.index') }}" class="sidebar-link {{ request()->routeIs('agents.*') ? 'active' : '' }}">
                <i class="bi bi-cpu"></i> Agents
            </a>
        @endcan
        <span class="sidebar-link disabled"><i class="bi bi-gear-wide-connected"></i> Services <span class="soon">Soon</span></span>
        @can('viewAny', App\Models\Application::class)
            <a href="{{ route('applications.index') }}" class="sidebar-link {{ request()->routeIs('applications.*') ? 'active' : '' }}">
                <i class="bi bi-app-indicator"></i> Applications
            </a>
        @else
            <span class="sidebar-link disabled"><i class="bi bi-app-indicator"></i> Applications <span class="soon">Soon</span></span>
        @endcan
        @can('viewAny', App\Models\Host::class)
            <a href="{{ route('metrics.index') }}" class="sidebar-link {{ request()->routeIs('metrics.*') ? 'active' : '' }}">
                <i class="bi bi-graph-up"></i> Metrics
            </a>
        @else
            <span class="sidebar-link disabled"><i class="bi bi-graph-up"></i> Metrics <span class="soon">Soon</span></span>
        @endcan

        <div class="sidebar-label">Logs</div>
        @can('viewAny', App\Models\LogEntry::class)
            <a href="{{ route('logs.index') }}" class="sidebar-link {{ request()->routeIs('logs.*') ? 'active' : '' }}">
                <i class="bi bi-search"></i> Log Explorer
            </a>
            <a href="{{ route('log-sources.index') }}" class="sidebar-link {{ request()->routeIs('log-sources.*') ? 'active' : '' }}">
                <i class="bi bi-collection"></i> Sources
            </a>
        @else
            <span class="sidebar-link disabled"><i class="bi bi-search"></i> Log Explorer <span class="soon">Soon</span></span>
            <span class="sidebar-link disabled"><i class="bi bi-collection"></i> Sources <span class="soon">Soon</span></span>
        @endcan

        <div class="sidebar-label">Operations</div>
        @can('viewAny', App\Models\Alert::class)
            <a href="{{ route('alerts.index') }}" class="sidebar-link {{ request()->routeIs('alerts.*') ? 'active' : '' }}">
                <i class="bi bi-exclamation-triangle"></i> Alerts
            </a>
            <a href="{{ route('alert-rules.index') }}" class="sidebar-link {{ request()->routeIs('alert-rules.*') ? 'active' : '' }}">
                <i class="bi bi-sliders2"></i> Alert Rules
            </a>
        @else
            <span class="sidebar-link disabled"><i class="bi bi-exclamation-triangle"></i> Alerts <span class="soon">Soon</span></span>
            <span class="sidebar-link disabled"><i class="bi bi-sliders2"></i> Alert Rules <span class="soon">Soon</span></span>
        @endcan
        @can('viewAny', App\Models\Incident::class)
            <a href="{{ route('incidents.index') }}" class="sidebar-link {{ request()->routeIs('incidents.*') ? 'active' : '' }}">
                <i class="bi bi-lightning"></i> Incidents
            </a>
        @else
            <span class="sidebar-link disabled"><i class="bi bi-lightning"></i> Incidents <span class="soon">Soon</span></span>
        @endcan
        @can('viewAny', App\Models\Dashboard::class)
            <a href="{{ route('dashboards.index') }}" class="sidebar-link {{ request()->routeIs('dashboards.*') ? 'active' : '' }}">
                <i class="bi bi-layout-wtf"></i> Dashboards
            </a>
        @else
            <span class="sidebar-link disabled"><i class="bi bi-layout-wtf"></i> Dashboards <span class="soon">Soon</span></span>
        @endcan
        <span class="sidebar-link disabled"><i class="bi bi-plugin"></i> Integrations <span class="soon">Soon</span></span>
        <span class="sidebar-link disabled"><i class="bi bi-file-earmark-bar-graph"></i> Reports <span class="soon">Soon</span></span>

        <div class="sidebar-label">Administration</div>
        <a href="{{ route('users.index') }}" class="sidebar-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
            <i class="bi bi-people"></i> Users
        </a>
        <a href="{{ route('roles.index') }}" class="sidebar-link {{ request()->routeIs('roles.*') ? 'active' : '' }}">
            <i class="bi bi-shield-lock"></i> Roles
        </a>
        @if ($currentOrganization && auth()->user()?->can('update', $currentOrganization))
            <a href="{{ route('settings.organization.edit') }}" class="sidebar-link {{ request()->routeIs('settings.organization.*') ? 'active' : '' }}">
                <i class="bi bi-building"></i> Organization
            </a>
        @endif
        <a href="{{ route('profile.edit') }}" class="sidebar-link {{ request()->routeIs('profile.*') ? 'active' : '' }}">
            <i class="bi bi-sliders"></i> Settings
        </a>

        @if (auth()->user()?->isSuperAdmin())
            <div class="sidebar-label">Platform</div>
            <a href="{{ route('organizations.index') }}" class="sidebar-link {{ request()->routeIs('organizations.*') ? 'active' : '' }}">
                <i class="bi bi-diagram-3"></i> Organizations
            </a>
        @endif
    </nav>
</aside>
