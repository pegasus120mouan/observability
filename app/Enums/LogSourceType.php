<?php

namespace App\Enums;

enum LogSourceType: string
{
    case Agent = 'agent';
    case File = 'file';
    case Syslog = 'syslog';
    case Journald = 'journald';
    case WindowsEvent = 'windows_event';

    public function label(): string
    {
        return match ($this) {
            self::Agent => 'Agent',
            self::File => 'File',
            self::Syslog => 'Syslog',
            self::Journald => 'Journald',
            self::WindowsEvent => 'Windows Event',
        };
    }
}
