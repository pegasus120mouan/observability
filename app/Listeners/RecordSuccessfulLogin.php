<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Login;

class RecordSuccessfulLogin
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->save();

        $this->auditLogger->log(AuditAction::Login, actor: $event->user);
    }
}
