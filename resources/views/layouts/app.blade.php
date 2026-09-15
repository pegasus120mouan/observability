@php
    $theme = request()->cookie('theme', 'dark');
    $user = auth()->user();
    $currentOrganization = $currentOrganization ?? null;
    $availableOrganizations = $availableOrganizations ?? collect();
    $currentRole = $user?->roleIn($currentOrganization);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Overview' }} · {{ config('platform.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell">
        @include('layouts.partials.sidebar')

        <div class="app-main">
            <header class="app-topbar">
                <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar">
                    <i class="bi bi-list"></i>
                </button>

                <div>
                    <h1 class="h5 mb-0">{{ $title ?? 'Overview' }}</h1>
                    @if ($currentOrganization)
                        <div class="text-secondary small">{{ $currentOrganization->name }}</div>
                    @endif
                </div>

                <div class="ms-auto d-flex align-items-center gap-2">
                    <form method="POST" action="{{ route('theme.update') }}">
                        @csrf
                        <input type="hidden" name="theme" value="{{ $theme === 'dark' ? 'light' : 'dark' }}">
                        <button class="btn btn-outline-secondary btn-sm" type="submit" title="Toggle theme">
                            <i class="bi {{ $theme === 'dark' ? 'bi-sun' : 'bi-moon-stars' }}"></i>
                        </button>
                    </form>

                    @if ($availableOrganizations->count() > 1 || $user?->isSuperAdmin())
                        <form method="POST" action="{{ route('current-organization.update') }}" class="d-none d-md-block">
                            @csrf
                            @method('PUT')
                            <select name="organization_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                @foreach ($availableOrganizations as $organization)
                                    <option value="{{ $organization->id }}" @selected($currentOrganization?->id === $organization->id)>
                                        {{ $organization->name }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    @endif

                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            {{ $user?->name }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li class="dropdown-item-text small text-secondary">
                                {{ $user?->isSuperAdmin() ? 'Super Admin' : $currentRole?->name->label() }}
                            </li>
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Profile</a></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="dropdown-item" type="submit">Sign out</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <main class="app-content">
                @include('layouts.partials.flash')
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
