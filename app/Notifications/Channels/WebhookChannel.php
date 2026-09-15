<?php

namespace App\Notifications\Channels;

use App\Notifications\AlertTriggeredNotification;
use Illuminate\Http\Client\RequestException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof AlertTriggeredNotification) {
            return;
        }

        $url = $notifiable->routeNotificationFor('webhook', $notification);

        if (! is_string($url) || $url === '') {
            return;
        }

        try {
            Http::connectTimeout(2)
                ->timeout(5)
                ->acceptJson()
                ->asJson()
                ->post($url, $notification->toWebhook($notifiable))
                ->throw();
        } catch (RequestException $exception) {
            Log::warning('Alert webhook delivery failed.', [
                'url' => $url,
                'status' => $exception->response?->status(),
            ]);
        }
    }
}
