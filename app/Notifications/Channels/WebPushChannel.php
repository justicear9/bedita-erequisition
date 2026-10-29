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

        $publicKey = config('services.webpush.public_key');
        $privateKey = config('services.webpush.private_key');

        if (! $publicKey || ! $privateKey) {
            Log::debug('WebPush skipped: VAPID keys not configured.');

            return;
        }

        $subscriptions = $notifiable->pushSubscriptions;
        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = $notification->toWebPush($notifiable);

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('services.webpush.subject') ?: config('app.url'),
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ]);
            $webPush->setReuseVAPIDHeaders(true);
        } catch (\Throwable $e) {
            Log::warning('WebPush init failed: '.$e->getMessage());

            return;
        }

        $json = json_encode([
            'title' => $payload['title'] ?? 'BEDITA eREQUISITION',
            'body' => $payload['body'] ?? '',
            'url' => $payload['url'] ?? url('/dashboard'),
            'tag' => $payload['tag'] ?? 'erequisition',
            'play_sound' => true,
        ], JSON_THROW_ON_ERROR);

        foreach ($subscriptions as $subscription) {
            /** @var PushSubscription $subscription */
            try {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'publicKey' => $subscription->public_key,
                        'authToken' => $subscription->auth_token,
                        'contentEncoding' => $subscription->content_encoding ?: 'aes128gcm',
                    ]),
                    $json,
                );
            } catch (\Throwable $e) {
                Log::warning('WebPush queue failed: '.$e->getMessage(), [
                    'subscription_id' => $subscription->id,
                ]);
            }
        }

        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()?->getUri()?->__toString();

            if ($report->isSubscriptionExpired()) {
                PushSubscription::query()->where('endpoint', $endpoint)->delete();
                Log::info('WebPush subscription expired and removed.', ['endpoint' => $endpoint]);

                continue;
            }

            if (! $report->isSuccess()) {
                Log::warning('WebPush delivery failed: '.$report->getReason(), [
                    'endpoint' => $endpoint,
                ]);
            }
        }
    }
}
