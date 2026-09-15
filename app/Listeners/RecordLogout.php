<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Logout;

class RecordLogout
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->auditLogger->log(AuditAction::Logout, actor: $event->user);
    }
}
