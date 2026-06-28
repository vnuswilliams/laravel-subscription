<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Vnuswilliams\Subscription\Enums\PeriodicityType;

final class Plan extends Model
{
    /** @var list<string> */
    protected $guarded = [];

    public function getTable(): string
    {
        return config('subscriptions.tables.plans', 'plans');
    }

    /** @return HasMany<PlanFeature, $this> */
    public function features(): HasMany
    {
        return $this->hasMany(
            config('subscriptions.models.plan_feature'),
            'plan_id'
        );
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(
            config('subscriptions.models.subscription'),
            'plan_id'
        );
    }

    public function isPermanent(): bool
    {
        return $this->periodicity_type === null;
    }

    public function hasTrial(): bool
    {
        return $this->trial_days > 0;
    }

    public function hasGrace(): bool
    {
        return $this->grace_days > 0;
    }

    public function periodicityType(): ?PeriodicityType
    {
        return $this->periodicity_type !== null
            ? PeriodicityType::from($this->periodicity_type)
            : null;
    }

    /** Calcule la date d'expiration à partir d'une date de départ. */
    public function expiresAt(\Carbon\CarbonInterface $from): ?\Carbon\CarbonInterface
    {
        $type = $this->periodicityType();

        if ($type === null || $this->periodicity === null) {
            return null;
        }

        return $type->addTo($from, (int) $this->periodicity);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price'        => 'decimal:' . (string) config('subscriptions.price.scale', 2),
            'is_active'   => 'boolean',
            'trial_days'  => 'integer',
            'grace_days'  => 'integer',
            'periodicity' => 'integer',
        ];
    }
}
