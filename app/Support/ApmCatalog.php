<?php

namespace App\Support;

use App\Enums\ApmSpanKind;
use App\Enums\ApplicationStatus;
use Illuminate\Support\Carbon;

final class ApmCatalog
{
    /**
     * @return array<string, string>
     */
    public static function ranges(): array
    {
        return [
            '15m' => 'Last 15 minutes',
            '1h' => 'Last 1 hour',
            '6h' => 'Last 6 hours',
            '24h' => 'Last 24 hours',
        ];
    }

    public static function defaultRange(): string
    {
        return '15m';
    }

    public static function hoursForRange(string $range): int
    {
        return match ($range) {
            '1h' => 1,
            '6h' => 6,
            '24h' => 24,
            default => 6,
        };
    }

    public static function fromForRange(string $range): Carbon
    {
        return match ($range) {
            '15m' => now()->subMinutes(15),
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '24h' => now()->subHours(24),
            default => now()->subMinutes(15),
        };
    }

    public static function bucketSecondsForRange(string $range): int
    {
        return match ($range) {
            '15m' => 15,
            '1h' => 60,
            '6h' => 300,
            '24h' => 900,
            default => 15,
        };
    }

    public static function bucketMinutesForRange(string $range): int
    {
        return max(1, (int) round(self::bucketSecondsForRange($range) / 60));
    }

    public static function warningErrorRate(): float
    {
        return (float) config('platform.apm.warning_error_rate', 1);
    }

    public static function criticalErrorRate(): float
    {
        return (float) config('platform.apm.critical_error_rate', 5);
    }

    public static function warningP95Ms(): int
    {
        return (int) config('platform.apm.warning_p95_ms', 1000);
    }

    public static function criticalP95Ms(): int
    {
        return (int) config('platform.apm.critical_p95_ms', 2000);
    }

    public static function maxApplicationsPerRequest(): int
    {
        return (int) config('platform.apm.max_applications_per_request', 20);
    }

    public static function maxHttpRequestsPerRequest(): int
    {
        return (int) config('platform.apm.max_http_requests_per_request', 200);
    }

    public static function recentRequestLimit(): int
    {
        return 50;
    }

    public static function errorRate(int $requestCount, int $errorCount): float
    {
        if ($requestCount < 1) {
            return 0.0;
        }

        return round(($errorCount / $requestCount) * 100, 2);
    }

    public static function statusFromSample(int $requestCount, int $errorCount, int $p95Ms): ApplicationStatus
    {
        if ($requestCount < 1) {
            return ApplicationStatus::Unknown;
        }

        $errorRate = self::errorRate($requestCount, $errorCount);

        if ($errorRate >= self::criticalErrorRate() || $p95Ms >= self::criticalP95Ms()) {
            return ApplicationStatus::Critical;
        }

        if ($errorRate >= self::warningErrorRate() || $p95Ms >= self::warningP95Ms()) {
            return ApplicationStatus::Warning;
        }

        return ApplicationStatus::Healthy;
    }

    public static function isAllowedStatusCode(string $code): bool
    {
        return (bool) preg_match('/^[1-5][0-9]{2}$/', $code);
    }

    /**
     * @param  list<int|float>  $values
     */
    public static function percentile(array $values, float $percentile): int
    {
        if ($values === []) {
            return 0;
        }

        sort($values);
        $index = (int) round((max(0, min(100, $percentile)) / 100) * (count($values) - 1));

        return (int) round($values[$index]);
    }

    public static function formatDurationUs(int $microseconds): string
    {
        if ($microseconds < 1) {
            return '—';
        }

        $milliseconds = $microseconds / 1000;

        if ($milliseconds >= 1000) {
            return number_format($milliseconds / 1000, $milliseconds >= 10_000 ? 0 : 2).' s';
        }

        if ($milliseconds >= 10) {
            return number_format($milliseconds, 0).' ms';
        }

        return number_format($milliseconds, 2).' ms';
    }

    /**
     * OpenTelemetry span kinds reserved for a later tracing store.
     *
     * @return list<ApmSpanKind>
     */
    public static function spanKinds(): array
    {
        return ApmSpanKind::cases();
    }
}
