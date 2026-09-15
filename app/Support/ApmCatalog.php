<?php

namespace App\Support;

use App\Enums\ApmSpanKind;
use App\Enums\ApplicationStatus;

final class ApmCatalog
{
    /**
     * @return array<string, int>
     */
    public static function ranges(): array
    {
        return [
            '1h' => 1,
            '6h' => 6,
            '24h' => 24,
        ];
    }

    public static function hoursForRange(string $range): int
    {
        return self::ranges()[$range] ?? 6;
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
     * OpenTelemetry span kinds reserved for a later tracing store.
     *
     * @return list<ApmSpanKind>
     */
    public static function spanKinds(): array
    {
        return ApmSpanKind::cases();
    }
}
