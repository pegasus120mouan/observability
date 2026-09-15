<?php

namespace App\Enums;

enum IncidentEventType: string
{
    case Created = 'created';
    case StatusChanged = 'status_changed';
    case Assigned = 'assigned';
    case Comment = 'comment';
    case AlertLinked = 'alert_linked';
    case Updated = 'updated';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::StatusChanged => 'Status changed',
            self::Assigned => 'Assigned',
            self::Comment => 'Comment',
            self::AlertLinked => 'Alert linked',
            self::Updated => 'Updated',
        };
    }
}
