<?php

namespace App\Providers;

use App\Notifications\Channels\WebhookChannel;
use App\Support\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        JsonResource::withoutWrapping();
        Paginator::useBootstrapFive();

        Password::defaults(fn (): Password => Password::min(8));

        Notification::extend('webhook', fn ($app) => $app->make(WebhookChannel::class));

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute((int) config('platform.auth.login_max_attempts'))->by(Str::transliterate(
                Str::lower($request->string('email')).'|'.$request->ip()
            ));
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('agent-register', function (Request $request) {
            return Limit::perMinute((int) config('platform.agents.register_rate_limit'))->by($request->ip());
        });

        RateLimiter::for('agent-heartbeat', function (Request $request) {
            $agentId = $request->header('X-Agent-Id') ?: $request->input('agent_id');

            return Limit::perMinute((int) config('platform.agents.heartbeat_rate_limit'))->by(
                $agentId ?: $request->ip()
            );
        });

        RateLimiter::for('agent-metrics', function (Request $request) {
            $agentId = $request->header('X-Agent-Id') ?: $request->input('agent_id');

            return Limit::perMinute((int) config('platform.agents.metrics_rate_limit'))->by(
                $agentId ?: $request->ip()
            );
        });

        RateLimiter::for('agent-logs', function (Request $request) {
            $agentId = $request->header('X-Agent-Id') ?: $request->input('agent_id');

            return Limit::perMinute((int) config('platform.agents.logs_rate_limit'))->by(
                $agentId ?: $request->ip()
            );
        });

        RateLimiter::for('agent-apm', function (Request $request) {
            $agentId = $request->header('X-Agent-Id') ?: $request->input('agent_id');

            return Limit::perMinute((int) config('platform.agents.apm_rate_limit'))->by(
                $agentId ?: $request->ip()
            );
        });
    }
}
