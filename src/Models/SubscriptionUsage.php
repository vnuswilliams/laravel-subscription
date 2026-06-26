<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SubscriptionUsage extends Model
{
    /** @var list<string> */
    protected $guarded = [];

    public function getTable(): string
    {
        return config('subscriptions.tables.subscription_usages', 'subscription_usages');
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(config('subscriptions.models.subscription'));
    }

    /** @return BelongsTo<PlanFeature, $this> */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(config('subscriptions.models.plan_feature'), 'feature_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'used' => 'integer',
        ];
    }
}
