<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Vnuswilliams\Subscription\Models\Plan;
use Vnuswilliams\Subscription\Models\Subscription;
use Vnuswilliams\Subscription\Models\SubscriptionUsage;
use Vnuswilliams\Subscription\Services\FeatureService;
use Vnuswilliams\Subscription\Services\SubscriptionService;

/**
 * À ajouter sur tout modèle souscripteur : User, Company, Team…
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasSubscriptions
{
    // ─────────────────────────────────────────────────────────────────────────
    //  Relation
    // ─────────────────────────────────────────────────────────────────────────

    /** @return MorphOne<Subscription, $this> */
    public function subscription(): MorphOne
    {
        return $this->morphOne(
            config('subscriptions.models.subscription'),
            'subscriber'
        )->latestOfMany();
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Souscription / actions
    // ─────────────────────────────────────────────────────────────────────────

    public function subscribeTo(string|Plan $plan, ?Carbon $expiration = null, bool $immediately = true, int|float|string|null $price = null): Subscription
    {
        return app(SubscriptionService::class)->subscribeTo($this, $plan, $expiration, $immediately, $price);
    }

    public function switchTo(string|Plan $plan, bool $immediately = true, int|float|string|null $price = null): Subscription
    {
        return app(SubscriptionService::class)->switchTo($this, $plan, $immediately, $price);
    }

    public function renewSubscription(): Subscription
    {
        return app(SubscriptionService::class)->renew($this);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  État
    // ─────────────────────────────────────────────────────────────────────────

    public function hasActiveSubscription(): bool
    {
        return app(SubscriptionService::class)->hasActiveSubscription($this);
    }

    public function currentPlan(): ?Plan
    {
        return app(SubscriptionService::class)->currentPlan($this);
    }

    public function subscriptionExpiresAt(): ?Carbon
    {
        return app(SubscriptionService::class)->expiresAt($this);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Features & Quotas
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * L'accès à une feature est-il autorisé ? Et le quota suffisant ?
     * Équivalent de canConsume() de ton ancien package.
     */
    public function canConsume(string $featureSlug, int $amount = 1): bool
    {
        return app(FeatureService::class)->canConsume($this, $featureSlug, $amount);
    }

    /**
     * Consomme $amount unités d'une feature consumable.
     */
    public function consume(string $featureSlug, int $amount = 1): SubscriptionUsage
    {
        return app(FeatureService::class)->consume($this, $featureSlug, $amount);
    }

    /**
     * Libère $amount unités (ex: suppression d'un employé → libère un slot).
     */
    public function release(string $featureSlug, int $amount = 1): SubscriptionUsage
    {
        return app(FeatureService::class)->release($this, $featureSlug, $amount);
    }

    /**
     * Solde restant d'une feature consumable.
     * PHP_INT_MAX si illimité.
     */
    public function balance(string $featureSlug): int
    {
        return app(FeatureService::class)->balance($this, $featureSlug);
    }

    /**
     * Charges totales allouées par le plan.
     */
    public function totalCharges(string $featureSlug): int
    {
        return app(FeatureService::class)->totalCharges($this, $featureSlug);
    }

    /**
     * Quantité consommée sur la période en cours.
     */
    public function usedCharges(string $featureSlug): int
    {
        return app(FeatureService::class)->usedCharges($this, $featureSlug);
    }
}
