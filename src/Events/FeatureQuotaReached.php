<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Vnuswilliams\Subscription\Models\PlanFeature;
use Vnuswilliams\Subscription\Models\Subscription;

final class FeatureQuotaReached
{
    use Dispatchable;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly PlanFeature  $feature,
    ) {}
}
