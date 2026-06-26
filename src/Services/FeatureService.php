<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Services;

use Illuminate\Database\Eloquent\Model;
use Vnuswilliams\Subscription\Enums\FeatureType;
use Vnuswilliams\Subscription\Events\FeatureQuotaReached;
use Vnuswilliams\Subscription\Exceptions\FeatureNotFoundException;
use Vnuswilliams\Subscription\Models\PlanFeature;
use Vnuswilliams\Subscription\Models\Subscription;
use Vnuswilliams\Subscription\Models\SubscriptionUsage;

final class FeatureService
{
    // ─────────────────────────────────────────────────────────────────────────
    //  Accès / vérification
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Le subscriber peut-il consommer $amount unités de $featureSlug ?
     *
     * - Feature booléenne  : $amount est ignoré, retourne true si la feature existe sur le plan.
     * - Feature consumable : vérifie que balance() >= $amount.
     * - charges === null    : quota illimité, toujours true.
     */
    public function canConsume(Model $subscriber, string $featureSlug, int $amount = 1): bool
    {
        $subscription = $this->activeSubscriptionFor($subscriber);

        if ($subscription === null) {
            return false;
        }

        $feature = $this->featureForSubscription($subscription, $featureSlug);

        if ($feature === null) {
            return false;
        }

        if ($feature->isBoolean()) {
            return true;
        }

        // Quota illimité
        if ($feature->isUnlimited()) {
            return true;
        }

        return $this->balance($subscriber, $featureSlug) >= $amount;
    }

    /**
     * Consomme $amount unités de $featureSlug.
     * Émet FeatureQuotaReached si le solde atteint 0 après consommation.
     *
     * @throws FeatureNotFoundException
     */
    public function consume(Model $subscriber, string $featureSlug, int $amount = 1): SubscriptionUsage
    {
        $subscription = $this->activeSubscriptionFor($subscriber);

        if ($subscription === null) {
            throw FeatureNotFoundException::withSlug($featureSlug);
        }

        $feature = $this->featureForSubscription($subscription, $featureSlug);

        if ($feature === null || $feature->isBoolean()) {
            throw FeatureNotFoundException::withSlug($featureSlug);
        }

        $usage = SubscriptionUsage::firstOrCreate(
            ['subscription_id' => $subscription->id, 'feature_id' => $feature->id],
            ['used' => 0],
        );

        $usage->increment('used', $amount);
        $usage->refresh();

        // Émet l'événement si le quota est épuisé
        if (! $feature->isUnlimited() && $this->balance($subscriber, $featureSlug) <= 0) {
            event(new FeatureQuotaReached($subscription, $feature));
        }

        return $usage;
    }

    /**
     * Libère $amount unités (décrémente used).
     * Utile pour libérer un slot quand on supprime un employé, par exemple.
     *
     * @throws FeatureNotFoundException
     */
    public function release(Model $subscriber, string $featureSlug, int $amount = 1): SubscriptionUsage
    {
        $subscription = $this->activeSubscriptionFor($subscriber);

        if ($subscription === null) {
            throw FeatureNotFoundException::withSlug($featureSlug);
        }

        $feature = $this->featureForSubscription($subscription, $featureSlug);

        if ($feature === null || $feature->isBoolean()) {
            throw FeatureNotFoundException::withSlug($featureSlug);
        }

        $usage = SubscriptionUsage::where('subscription_id', $subscription->id)
            ->where('feature_id', $feature->id)
            ->first();

        if ($usage === null) {
            return new SubscriptionUsage(['used' => 0]);
        }

        $newUsed = max(0, $usage->used - $amount);
        $usage->update(['used' => $newUsed]);

        return $usage;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Soldes
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retourne le solde restant (charges - used).
     * Retourne PHP_INT_MAX si quota illimité.
     * Retourne 0 si pas d'abonnement actif ou feature introuvable.
     */
    public function balance(Model $subscriber, string $featureSlug): int
    {
        $subscription = $this->activeSubscriptionFor($subscriber);

        if ($subscription === null) {
            return 0;
        }

        $feature = $this->featureForSubscription($subscription, $featureSlug);

        if ($feature === null) {
            return 0;
        }

        if ($feature->isUnlimited()) {
            return PHP_INT_MAX;
        }

        $used = SubscriptionUsage::where('subscription_id', $subscription->id)
            ->where('feature_id', $feature->id)
            ->value('used') ?? 0;

        return max(0, (int) $feature->charges - (int) $used);
    }

    /**
     * Charges totales allouées par le plan pour cette feature.
     * PHP_INT_MAX si illimité, 0 si introuvable.
     */
    public function totalCharges(Model $subscriber, string $featureSlug): int
    {
        $subscription = $this->activeSubscriptionFor($subscriber);

        if ($subscription === null) {
            return 0;
        }

        $feature = $this->featureForSubscription($subscription, $featureSlug);

        if ($feature === null) {
            return 0;
        }

        return $feature->isUnlimited() ? PHP_INT_MAX : (int) $feature->charges;
    }

    /**
     * Quantité consommée sur la période en cours.
     */
    public function usedCharges(Model $subscriber, string $featureSlug): int
    {
        $subscription = $this->activeSubscriptionFor($subscriber);

        if ($subscription === null) {
            return 0;
        }

        $feature = $this->featureForSubscription($subscription, $featureSlug);

        if ($feature === null) {
            return 0;
        }

        return (int) (SubscriptionUsage::where('subscription_id', $subscription->id)
            ->where('feature_id', $feature->id)
            ->value('used') ?? 0);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers privés
    // ─────────────────────────────────────────────────────────────────────────

    private function activeSubscriptionFor(Model $subscriber): ?Subscription
    {
        /** @var Subscription|null $sub */
        $sub = $subscriber->subscription()->first(); // @phpstan-ignore-line

        return ($sub instanceof Subscription && $sub->hasAccess()) ? $sub : null;
    }

    private function featureForSubscription(Subscription $subscription, string $slug): ?PlanFeature
    {
        return $subscription->plan
            ->features()
            ->where('slug', $slug)
            ->first();
    }
}
