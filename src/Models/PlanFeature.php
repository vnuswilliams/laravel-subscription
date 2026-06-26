<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Vnuswilliams\Subscription\Enums\FeatureType;

final class PlanFeature extends Model
{
    /** @var list<string> */
    protected $guarded = [];

    public function getTable(): string
    {
        return config('subscriptions.tables.plan_features', 'plan_features');
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(config('subscriptions.models.plan'));
    }

    /** @return HasMany<SubscriptionUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(config('subscriptions.models.subscription_usage'), 'feature_id');
    }

    public function isConsumable(): bool
    {
        return $this->type === FeatureType::Consumable->value;
    }

    public function isBoolean(): bool
    {
        return $this->type === FeatureType::Boolean->value;
    }

    public function isUnlimited(): bool
    {
        return $this->charges === null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'charges' => 'integer',
        ];
    }
}
