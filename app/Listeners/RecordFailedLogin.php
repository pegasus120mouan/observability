<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;

class RecordFailedLogin
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(Failed $event): void
    {
        $this->auditLogger->log(
            AuditAction::LoginFailed,
            actor: null,
            newValues: [
                'email' => $event->credentials['email'] ?? null,
                'guard' => $event->guard,
            ],
        );
    }
}
