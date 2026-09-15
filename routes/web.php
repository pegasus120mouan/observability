<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AlertRuleController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CurrentOrganizationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardWidgetController;
use App\Http\Controllers\HostController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\LogsController;
use App\Http\Controllers\LogSourceController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationSettingsController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('overview')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::post('theme', ThemeController::class)->name('theme.update');

Route::middleware(['auth', 'active.user', 'tenant'])->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('overview', OverviewController::class)->name('overview');

    Route::put('current-organization', [CurrentOrganizationController::class, 'update'])
        ->name('current-organization.update');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('roles', RoleController::class)->name('roles.index');

    Route::get('settings/organization', [OrganizationSettingsController::class, 'edit'])
        ->name('settings.organization.edit');
    Route::put('settings/organization', [OrganizationSettingsController::class, 'update'])
        ->name('settings.organization.update');

    Route::resource('organizations', OrganizationController::class)->except(['show']);
    Route::resource('users', UserController::class)->except(['show']);

    Route::get('hosts', [HostController::class, 'index'])->name('hosts.index');
    Route::get('hosts/{host}', [HostController::class, 'show'])->name('hosts.show');
    Route::put('hosts/{host}', [HostController::class, 'update'])->name('hosts.update');

    Route::get('applications', [ApplicationController::class, 'index'])->name('applications.index');
    Route::get('applications/create', [ApplicationController::class, 'create'])->name('applications.create');
    Route::post('applications', [ApplicationController::class, 'store'])->name('applications.store');
    Route::get('applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
    Route::get('applications/{application}/edit', [ApplicationController::class, 'edit'])->name('applications.edit');
    Route::put('applications/{application}', [ApplicationController::class, 'update'])->name('applications.update');
    Route::delete('applications/{application}', [ApplicationController::class, 'destroy'])->name('applications.destroy');

    Route::get('metrics', MetricsController::class)->name('metrics.index');

    Route::get('alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::get('alerts/{alert}', [AlertController::class, 'show'])->name('alerts.show');
    Route::post('alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge'])->name('alerts.acknowledge');
    Route::post('alerts/{alert}/resolve', [AlertController::class, 'resolve'])->name('alerts.resolve');
    Route::post('alerts/{alert}/incident', [IncidentController::class, 'fromAlert'])->name('alerts.incident');
    Route::resource('alert-rules', AlertRuleController::class)->except(['show']);

    Route::get('incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('incidents/create', [IncidentController::class, 'create'])->name('incidents.create');
    Route::post('incidents', [IncidentController::class, 'store'])->name('incidents.store');
    Route::get('incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
    Route::put('incidents/{incident}', [IncidentController::class, 'update'])->name('incidents.update');
    Route::post('incidents/{incident}/comment', [IncidentController::class, 'comment'])->name('incidents.comment');

    Route::get('dashboards', [DashboardController::class, 'index'])->name('dashboards.index');
    Route::get('dashboards/create', [DashboardController::class, 'create'])->name('dashboards.create');
    Route::post('dashboards', [DashboardController::class, 'store'])->name('dashboards.store');
    Route::get('dashboards/{dashboard}', [DashboardController::class, 'show'])->name('dashboards.show');
    Route::get('dashboards/{dashboard}/edit', [DashboardController::class, 'edit'])->name('dashboards.edit');
    Route::put('dashboards/{dashboard}', [DashboardController::class, 'update'])->name('dashboards.update');
    Route::delete('dashboards/{dashboard}', [DashboardController::class, 'destroy'])->name('dashboards.destroy');
    Route::post('dashboards/{dashboard}/widgets', [DashboardWidgetController::class, 'store'])->name('dashboards.widgets.store');
    Route::put('dashboards/{dashboard}/widgets/{widget}', [DashboardWidgetController::class, 'update'])->scopeBindings()->name('dashboards.widgets.update');
    Route::delete('dashboards/{dashboard}/widgets/{widget}', [DashboardWidgetController::class, 'destroy'])->scopeBindings()->name('dashboards.widgets.destroy');

    Route::get('logs', [LogsController::class, 'index'])->name('logs.index');
    Route::get('logs/export', [LogsController::class, 'export'])->name('logs.export');
    Route::get('log-sources', [LogSourceController::class, 'index'])->name('log-sources.index');
    Route::post('log-sources/{log_source}/pause', [LogSourceController::class, 'pause'])->name('log-sources.pause');
    Route::post('log-sources/{log_source}/resume', [LogSourceController::class, 'resume'])->name('log-sources.resume');

    Route::get('agents', [AgentController::class, 'index'])->name('agents.index');
    Route::post('agents/enrollment-tokens', [AgentController::class, 'storeToken'])->name('agents.tokens.store');
    Route::delete('agents/enrollment-tokens/{enrollment_token}', [AgentController::class, 'revokeToken'])->name('agents.tokens.destroy');
    Route::post('agents/{agent}/rotate-key', [AgentController::class, 'rotate'])->name('agents.rotate');
    Route::post('agents/{agent}/revoke', [AgentController::class, 'revoke'])->name('agents.revoke');
});
