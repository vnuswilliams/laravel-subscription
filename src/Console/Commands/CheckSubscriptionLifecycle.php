<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Vnuswilliams\Subscription\Enums\SubscriptionStatus;
use Vnuswilliams\Subscription\Events\SubscriptionExpired;
use Vnuswilliams\Subscription\Models\Subscription;
use Vnuswilliams\Subscription\Services\SubscriptionService;

final class CheckSubscriptionLifecycle extends Command
{
    protected $signature   = 'subscription:check-lifecycle';
    protected $description = 'Transitions subscriptions to grace period or expired based on dates.';

    public function handle(SubscriptionService $service): int
    {
        $processed = 0;

        // 1. Active → OnGracePeriod  (ends_at dépassé, grace disponible)
        Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->with('plan')
            ->each(function (Subscription $subscription) use ($service, &$processed): void {
                if ($service->transitionToGraceIfNeeded($subscription)) {
                    $this->line("  → Grace: subscription #{$subscription->id}");
                    $processed++;
                }
            });

        // 2. Active/OnGracePeriod → Expired  (grace_ends_at dépassé ou pas de grâce)
        Subscription::query()
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::OnGracePeriod->value,
            ])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->where(function ($q): void {
                $q->whereNull('grace_ends_at')
                  ->orWhere('grace_ends_at', '<=', now());
            })
            ->each(function (Subscription $subscription) use (&$processed): void {
                $subscription->update(['status' => SubscriptionStatus::Expired->value]);
                event(new SubscriptionExpired($subscription));
                $this->line("  → Expired: subscription #{$subscription->id}");
                $processed++;
            });

        $this->info("subscription:check-lifecycle done. {$processed} subscription(s) transitioned.");

        return self::SUCCESS;
    }
}
