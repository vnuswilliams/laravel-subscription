<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Vnuswilliams\Subscription\Contracts\SubscriberResolver;
use Vnuswilliams\Subscription\Exceptions\InvalidSubscriberException;
use Vnuswilliams\Subscription\Models\Plan;
use Vnuswilliams\Subscription\Models\Subscription;
use Vnuswilliams\Subscription\Models\SubscriptionUsage;
use Vnuswilliams\Subscription\Traits\HasSubscriptions;

if (! function_exists('subscription_resolve_subscriber')) {
    /**
     * @internal
     */
    function subscription_resolve_subscriber(?Model $subscriber = null): Model
    {
        $subscriber ??= app(SubscriberResolver::class)->resolve();

        if (! $subscriber instanceof Model) {
            throw InvalidSubscriberException::notResolved();
        }

        if (! in_array(HasSubscriptions::class, class_uses_recursive($subscriber), true)) {
            throw InvalidSubscriberException::missingSubscriptionsTrait(get_class($subscriber));
        }

        return $subscriber;
    }
}

if (! function_exists('subscribeTo')) {
    function subscribeTo(
        string|Plan $plan,
        ?Carbon $expiration = null,
        bool $immediately = true,
        int|float|string|null $price = null,
        ?Model $subscriber = null,
    ): Subscription {
        return subscription_resolve_subscriber($subscriber)
            ->subscribeTo($plan, $expiration, $immediately, $price);
    }
}

if (! function_exists('switchTo')) {
    function switchTo(
        string|Plan $plan,
        bool $immediately = true,
        int|float|string|null $price = null,
        ?Model $subscriber = null,
    ): Subscription {
        return subscription_resolve_subscriber($subscriber)
            ->switchTo($plan, $immediately, $price);
    }
}

if (! function_exists('renewSubscription')) {
    function renewSubscription(?Model $subscriber = null): Subscription
    {
        return subscription_resolve_subscriber($subscriber)->renewSubscription();
    }
}

if (! function_exists('hasActiveSubscription')) {
    function hasActiveSubscription(?Model $subscriber = null): bool
    {
        return subscription_resolve_subscriber($subscriber)->hasActiveSubscription();
    }
}

if (! function_exists('currentPlan')) {
    function currentPlan(?Model $subscriber = null): ?Plan
    {
        return subscription_resolve_subscriber($subscriber)->currentPlan();
    }
}

if (! function_exists('subscriptionExpiresAt')) {
    function subscriptionExpiresAt(?Model $subscriber = null): ?Carbon
    {
        return subscription_resolve_subscriber($subscriber)->subscriptionExpiresAt();
    }
}

if (! function_exists('canConsume')) {
    function canConsume(string $featureSlug, int $amount = 1, ?Model $subscriber = null): bool
    {
        return subscription_resolve_subscriber($subscriber)->canConsume($featureSlug, $amount);
    }
}

if (! function_exists('consume')) {
    function consume(string $featureSlug, int $amount = 1, ?Model $subscriber = null): SubscriptionUsage
    {
        return subscription_resolve_subscriber($subscriber)->consume($featureSlug, $amount);
    }
}

if (! function_exists('release')) {
    function release(string $featureSlug, int $amount = 1, ?Model $subscriber = null): SubscriptionUsage
    {
        return subscription_resolve_subscriber($subscriber)->release($featureSlug, $amount);
    }
}

if (! function_exists('balance')) {
    function balance(string $featureSlug, ?Model $subscriber = null): int
    {
        return subscription_resolve_subscriber($subscriber)->balance($featureSlug);
    }
}

if (! function_exists('totalCharges')) {
    function totalCharges(string $featureSlug, ?Model $subscriber = null): int
    {
        return subscription_resolve_subscriber($subscriber)->totalCharges($featureSlug);
    }
}

if (! function_exists('usedCharges')) {
    function usedCharges(string $featureSlug, ?Model $subscriber = null): int
    {
        return subscription_resolve_subscriber($subscriber)->usedCharges($featureSlug);
    }
}
