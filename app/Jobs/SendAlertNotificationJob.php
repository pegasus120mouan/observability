<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Notifications\AlertTriggeredNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class SendAlertNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $alertId) {}

    public function handle(): void
    {
        $alert = Alert::query()
            ->withoutGlobalScopes()
            ->with(['rule', 'host'])
            ->find($this->alertId);

        if ($alert === null || $alert->rule === null) {
            return;
        }

        foreach ($alert->rule->channels() as $channel) {
            if ($channel['channel'] === 'mail') {
                Notification::route('mail', $channel['target'])
                    ->notify(new AlertTriggeredNotification($alert, 'mail'));

                continue;
            }

            if ($channel['channel'] === 'webhook') {
                Notification::route('webhook', $channel['target'])
                    ->notify(new AlertTriggeredNotification($alert, 'webhook'));
            }
        }
    }
}
