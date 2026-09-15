<?php

namespace App\Enums;

enum DashboardWidgetType: string
{
    case Stat = 'stat';
    case Timeseries = 'timeseries';
    case Hosts = 'hosts';
    case Alerts = 'alerts';
    case Incidents = 'incidents';
    case Logs = 'logs';

    public function label(): string
    {
        return match ($this) {
            self::Stat => 'Statistic',
            self::Timeseries => 'Timeseries',
            self::Hosts => 'Hosts',
            self::Alerts => 'Alerts',
            self::Incidents => 'Incidents',
            self::Logs => 'Logs',
        };
    }
}
