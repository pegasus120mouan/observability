<?php

namespace App\Enums;

enum DashboardStatMetric: string
{
    case Hosts = 'hosts';
    case HostsOnline = 'hosts_online';
    case OpenAlerts = 'open_alerts';
    case OpenIncidents = 'open_incidents';
    case ErrorLogs = 'error_logs';

    public function label(): string
    {
        return match ($this) {
            self::Hosts => 'Hosts',
            self::HostsOnline => 'Online hosts',
            self::OpenAlerts => 'Open alerts',
            self::OpenIncidents => 'Open incidents',
            self::ErrorLogs => 'Error logs (24h)',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Hosts => 'In this organization',
            self::HostsOnline => 'Reporting a recent heartbeat',
            self::OpenAlerts => 'Needs attention',
            self::OpenIncidents => 'Needs response',
            self::ErrorLogs => 'Last 24 hours',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Hosts => 'bi-hdd-network',
            self::HostsOnline => 'bi-heart-pulse',
            self::OpenAlerts => 'bi-exclamation-triangle',
            self::OpenIncidents => 'bi-lightning',
            self::ErrorLogs => 'bi-journal-x',
        };
    }
}
