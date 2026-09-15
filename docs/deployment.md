# Deployment

## Local (Laragon)

1. Point the site document root to `public/`.
2. PHP 8.3, MySQL 8, Composer, Node.js.
3. Copy `.env.example` to `.env` and set `DB_*`.
4. `php artisan migrate --seed`
5. `npm run build` (or `npm run dev` while developing)
6. Scheduler: `php artisan schedule:work` so `hosts:mark-offline` and `alerts:evaluate` run every minute, and `metrics:prune` / `logs:prune` run daily
7. Queue: `php artisan queue:work` so `EvaluateAlertJob` and `SendAlertNotificationJob` run (tests use `sync`)

## Ubuntu target (later)

- Nginx or Apache vhost → `public/`
- `php-fpm` 8.3
- MySQL 8
- Redis for cache/queue when those phases land
- Scheduler: `* * * * * php /path/to/artisan schedule:run`
- Do not run `migrate --seed` in production after the first demo

Docker is intentionally postponed. Keep the app 12-factor (config via env) so a container image can wrap the same tree later.
