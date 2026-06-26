<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Facades;

use Illuminate\Support\Facades\Facade;
use Vnuswilliams\Subscription\SubscriptionManager;

/**
 * @method static \Vnuswilliams\Subscription\Models\Subscription  subscribeTo(\Illuminate\Database\Eloquent\Model $subscriber, string|\Vnuswilliams\Subscription\Models\Plan $plan, ?\Carbon\Carbon $expiration = null, bool $immediately = true)
 * @method static \Vnuswilliams\Subscription\Models\Subscription  switchTo(\Illuminate\Database\Eloquent\Model $subscriber, string|\Vnuswilliams\Subscription\Models\Plan $plan, bool $immediately = true)
 * @method static \Vnuswilliams\Subscription\Models\Subscription  renew(\Illuminate\Database\Eloquent\Model $subscriber)
 * @method static \Vnuswilliams\Subscription\Models\Subscription  cancel(\Illuminate\Database\Eloquent\Model $subscriber)
 * @method static \Vnuswilliams\Subscription\Models\Subscription  suppress(\Illuminate\Database\Eloquent\Model $subscriber)
 * @method static bool                                             hasActiveSubscription(\Illuminate\Database\Eloquent\Model $subscriber)
 * @method static \Vnuswilliams\Subscription\Models\Plan|null     currentPlan(\Illuminate\Database\Eloquent\Model $subscriber)
 * @method static \Carbon\Carbon|null                             expiresAt(\Illuminate\Database\Eloquent\Model $subscriber)
 * @method static \Vnuswilliams\Subscription\Models\Plan          resolvePlan(string|\Vnuswilliams\Subscription\Models\Plan $plan)
 * @method static bool                                             canConsume(\Illuminate\Database\Eloquent\Model $subscriber, string $featureSlug, int $amount = 1)
 * @method static \Vnuswilliams\Subscription\Models\SubscriptionUsage consume(\Illuminate\Database\Eloquent\Model $subscriber, string $featureSlug, int $amount = 1)
 * @method static \Vnuswilliams\Subscription\Models\SubscriptionUsage release(\Illuminate\Database\Eloquent\Model $subscriber, string $featureSlug, int $amount = 1)
 * @method static int                                              balance(\Illuminate\Database\Eloquent\Model $subscriber, string $featureSlug)
 * @method static int                                              totalCharges(\Illuminate\Database\Eloquent\Model $subscriber, string $featureSlug)
 * @method static int                                              usedCharges(\Illuminate\Database\Eloquent\Model $subscriber, string $featureSlug)
 *
 * @see \Vnuswilliams\Subscription\SubscriptionManager
 */
final class Subscription extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SubscriptionManager::class;
    }
}
