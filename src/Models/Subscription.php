<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Vnuswilliams\Subscription\Enums\SubscriptionStatus;
use Vnuswilliams\Subscription\Events\SubscriptionCanceled;
use Vnuswilliams\Subscription\Events\SubscriptionExpired;

final class Subscription extends Model
{
    /** @var list<string> */
    protected $guarded = [];

    public function getTable(): string
    {
        return config('subscriptions.tables.subscriptions', 'subscriptions');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Relations
    // ─────────────────────────────────────────────────────────────────────────

    /** @return MorphTo<Model, $this> */
    public function subscriber(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(config('subscriptions.models.plan'));
    }

    /** @return HasMany<SubscriptionUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(config('subscriptions.models.subscription_usage'), 'subscription_id');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  État calculé
    // ─────────────────────────────────────────────────────────────────────────

    public function status(): SubscriptionStatus
    {
        return SubscriptionStatus::from($this->status);
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active->value
            && ($this->ends_at === null || Carbon::parse($this->ends_at)->isFuture());
    }

    public function isOnTrial(): bool
    {
        return $this->trial_ends_at !== null
            && Carbon::parse($this->trial_ends_at)->isFuture();
    }

    public function isOnGracePeriod(): bool
    {
        return $this->grace_ends_at !== null
            && Carbon::parse($this->grace_ends_at)->isFuture()
            && ($this->ends_at !== null && Carbon::parse($this->ends_at)->isPast());
    }

    public function isCanceled(): bool
    {
        return $this->canceled_at !== null;
    }

    public function isSuppressed(): bool
    {
        return $this->suppressed_at !== null;
    }

    public function isExpired(): bool
    {
        return ! $this->isActive()
            && ! $this->isOnTrial()
            && ! $this->isOnGracePeriod()
            && ! $this->isSuppressed() === false;
    }

    /**
     * L'abonnement donne-t-il encore accès aux ressources ?
     * (actif, essai, ou en grâce — même si canceled)
     */
    public function hasAccess(): bool
    {
        if ($this->isSuppressed()) {
            return false;
        }

        return $this->isActive() || $this->isOnTrial() || $this->isOnGracePeriod();
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Actions
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Annule l'abonnement : maintient l'accès jusqu'à ends_at + grace.
     */
    public function cancel(): static
    {
        $this->update([
            'canceled_at' => now(),
            'status'      => SubscriptionStatus::Canceled->value,
        ]);

        event(new SubscriptionCanceled($this));

        return $this;
    }

    /**
     * Coupe l'accès immédiatement (sans attendre ends_at ni la grâce).
     */
    public function suppress(): static
    {
        $this->update([
            'suppressed_at' => now(),
            'status'        => SubscriptionStatus::Expired->value,
        ]);

        event(new SubscriptionExpired($this));

        return $this;
    }

    /**
     * Renouvelle l'abonnement à partir de maintenant.
     */
    public function renew(): static
    {
        $plan     = $this->plan;
        $endsAt   = $plan->expiresAt(now());

        $this->update([
            'status'         => SubscriptionStatus::Active->value,
            'starts_at'      => now(),
            'ends_at'        => $endsAt,
            'grace_ends_at'  => $endsAt && $plan->grace_days > 0
                ? Carbon::parse($endsAt)->addDays($plan->grace_days)
                : null,
            'canceled_at'    => null,
            'suppressed_at'  => null,
        ]);

        return $this;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price'        => 'decimal:' . (string) config('subscriptions.price.scale', 2),
            'trial_ends_at'  => 'datetime',
            'starts_at'      => 'datetime',
            'ends_at'        => 'datetime',
            'grace_ends_at'  => 'datetime',
            'canceled_at'    => 'datetime',
            'suppressed_at'  => 'datetime',
        ];
    }
}
