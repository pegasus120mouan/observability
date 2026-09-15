<?php

namespace App\Notifications;

use App\Models\Alert;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertTriggeredNotification extends Notification
{
    public function __construct(public Alert $alert, public string $channel = 'mail') {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return [$this->channel];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $product = (string) config('platform.name');

        return (new MailMessage)
            ->subject('['.config('platform.short_name').'] '.$this->alert->severity->label().': '.$this->alert->title)
            ->greeting($product.' alert')
            ->line($this->alert->description ?: $this->alert->title)
            ->line('Host: '.($this->alert->host?->hostname ?? 'unknown'))
            ->line('Severity: '.$this->alert->severity->label())
            ->action('Open alert', route('alerts.show', $this->alert));
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebhook(object $notifiable): array
    {
        return [
            'event' => 'alert.triggered',
            'product' => config('platform.short_name'),
            'alert' => [
                'id' => $this->alert->id,
                'title' => $this->alert->title,
                'description' => $this->alert->description,
                'severity' => $this->alert->severity->value,
                'status' => $this->alert->status->value,
                'host' => $this->alert->host?->hostname,
                'triggered_at' => $this->alert->triggered_at?->toIso8601String(),
                'metadata' => $this->alert->metadata,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toWebhook($notifiable);
    }
}
