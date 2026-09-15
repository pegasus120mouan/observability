<?php

namespace App\Policies;

use App\Models\LogEntry;
use App\Models\User;
use App\Support\PermissionCatalog;

class LogEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionCatalog::LOGS_VIEW);
    }

    public function view(User $user, LogEntry $logEntry): bool
    {
        return $user->hasPermission(PermissionCatalog::LOGS_VIEW);
    }
}
