<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Vnuswilliams\Subscription\Models\Plan;
use Vnuswilliams\Subscription\Models\Subscription;
use Vnuswilliams\Subscription\Models\SubscriptionUsage;
use Vnuswilliams\Subscription\Services\FeatureService;
use Vnuswilliams\Subscription\Services\SubscriptionService;

/**
 * Point d'entrée unique du package.
 * Accessible via Facade ou injection directe.
 *
 * @see \Vnuswilliams\Subscription\Facades\Subscription
 */
final class SubscriptionManager
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly FeatureService      $features,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    //  Souscription / cycle de vie
    // ─────────────────────────────────────────────────────────────────────────

    public function subscribeTo(Model $subscriber, string|Plan $plan, ?Carbon $expiration = null, bool $immediately = true): Subscription
    {
        return $this->subscriptions->subscribeTo($subscriber, $plan, $expiration, $immediately);
    }

    public function switchTo(Model $subscriber, string|Plan $plan, bool $immediately = true): Subscription
    {
        return $this->subscriptions->switchTo($subscriber, $plan, $immediately);
    }

    public function renew(Model $subscriber): Subscription
    {
        return $this->subscriptions->renew($subscriber);
    }

    public function cancel(Model $subscriber): Subscription
    {
        return $this->subscriptions->cancel($subscriber);
    }

    public function suppress(Model $subscriber): Subscription
    {
        return $this->subscriptions->suppress($subscriber);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  État de l'abonnement
    // ─────────────────────────────────────────────────────────────────────────

    public function hasActiveSubscription(Model $subscriber): bool
    {
        return $this->subscriptions->hasActiveSubscription($subscriber);
    }

    public function currentPlan(Model $subscriber): ?Plan
    {
        return $this->subscriptions->currentPlan($subscriber);
    }

    public function expiresAt(Model $subscriber): ?Carbon
    {
        return $this->subscriptions->expiresAt($subscriber);
    }

    public function resolvePlan(string|Plan $plan): Plan
    {
        return $this->subscriptions->resolvePlan($plan);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Features & Quotas
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Vérifie si le subscriber peut consommer $amount unités de $featureSlug.
     * Pour une feature booléenne, $amount est ignoré.
     */
    public function canConsume(Model $subscriber, string $featureSlug, int $amount = 1): bool
    {
        return $this->features->canConsume($subscriber, $featureSlug, $amount);
    }

    /**
     * Consomme $amount unités d'une feature consumable.
     */
    public function consume(Model $subscriber, string $featureSlug, int $amount = 1): SubscriptionUsage
    {
        return $this->features->consume($subscriber, $featureSlug, $amount);
    }

    /**
     * Libère $amount unités (ex: suppression d'un employé).
     */
    public function release(Model $subscriber, string $featureSlug, int $amount = 1): SubscriptionUsage
    {
        return $this->features->release($subscriber, $featureSlug, $amount);
    }

    /**
     * Solde restant. PHP_INT_MAX si illimité.
     */
    public function balance(Model $subscriber, string $featureSlug): int
    {
        return $this->features->balance($subscriber, $featureSlug);
    }

    /**
     * Total des charges allouées par le plan.
     */
    public function totalCharges(Model $subscriber, string $featureSlug): int
    {
        return $this->features->totalCharges($subscriber, $featureSlug);
    }

    /**
     * Quantité consommée sur la période en cours.
     */
    public function usedCharges(Model $subscriber, string $featureSlug): int
    {
        return $this->features->usedCharges($subscriber, $featureSlug);
    }
}
