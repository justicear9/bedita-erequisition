<?php

namespace App\Notifications\Channels;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User) {
            return;
        }

        if (! method_exists($notification, 'toWebPush')) {
            return;
        }

        $payload = $notification->toWebPush($notifiable);
        $publicKey = config('services.webpush.public_key');
        $privateKey = config('services.webpush.private_key');

        if (! $publicKey || ! $privateKey) {
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('services.webpush.subject', config('app.url')),
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('WebPush init failed: '.$e->getMessage());

            return;
        }

        $json = json_encode([
            'title' => $payload['title'] ?? config('app.name'),
            'body' => $payload['body'] ?? '',
            'url' => $payload['url'] ?? url('/'),
            'tag' => $payload['tag'] ?? 'erequisition',
            'play_sound' => true,
        ]);

        $subscriptions = $notifiable->pushSubscriptions;

        foreach ($subscriptions as $subscription) {
            /** @var PushSubscription $subscription */
            try {
                $report = $webPush->sendOneNotification(
                    Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'publicKey' => $subscription->public_key,
                        'authToken' => $subscription->auth_token,
                        'contentEncoding' => $subscription->content_encoding ?: 'aesgcm',
                    ]),
                    $json,
                );

                if ($report->isSubscriptionExpired()) {
                    $subscription->delete();
                }
            } catch (\Throwable $e) {
                Log::warning('WebPush send failed: '.$e->getMessage());
            }
        }
    }
}
