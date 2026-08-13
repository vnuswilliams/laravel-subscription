<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Traits;

use Carbon\Carbon;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Auth;
use Vnuswilliams\Subscription\Models\Plan;
use Vnuswilliams\Subscription\Models\Subscription;
use Vnuswilliams\Subscription\Models\SubscriptionUsage;
use Vnuswilliams\Subscription\SubscriptionManager;

/**
 * À ajouter sur tout modèle souscripteur : User, Company, Team…
 *
 * Les opérations de lecture et de consommation passent par SubscriptionManager.
 * Ainsi, lorsqu'un subject resolver est configuré, un membre de team utilise
 * automatiquement l'abonnement et le quota du propriétaire de sa team.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasSubscriptions
{
    // ─────────────────────────────────────────────────────────────────────────
    //  Utilisateur authentifié
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retourne l'utilisateur authentifié s'il utilise ce trait.
     *
     * Exemple : `User::currentUser()?->currentPlan()`.
     *
     * @return static|null
     */
    public static function currentUser(): ?static
    {
        $user = Auth::user();

        return $user instanceof static ? $user : null;
    }

    /**
     * Retourne l'utilisateur authentifié ou lève une exception s'il n'y en a pas.
     *
     * @throws AuthenticationException
     */
    public static function currentUserOrFail(): static
    {
        $user = static::currentUser();

        if ($user === null) {
            throw new AuthenticationException('Unauthenticated.');
        }

        return $user;
    }

    /**
     * Indique si ce modèle est l'utilisateur actuellement authentifié.
     */
    public function isCurrentUser(): bool
    {
        $user = Auth::user();

        return $user instanceof Model && $this->is($user);
    }

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
    //  Souscription / actions (toujours sur ce modèle, jamais résolues)
    // ─────────────────────────────────────────────────────────────────────────

    public function subscribeTo(
        string|Plan $plan,
        ?Carbon $expiration = null,
        bool $immediately = true,
        int|float|string|null $price = null
    ): Subscription {
        return app(SubscriptionManager::class)->subscribeTo($this, $plan, $expiration, $immediately, $price);
    }

    public function switchTo(
        string|Plan $plan,
        bool $immediately = true,
        int|float|string|null $price = null
    ): Subscription {
        return app(SubscriptionManager::class)->switchTo($this, $plan, $immediately, $price);
    }

    public function renewSubscription(): Subscription
    {
        return app(SubscriptionManager::class)->renew($this);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  État (résolution vers le propriétaire de l'abonnement)
    // ─────────────────────────────────────────────────────────────────────────

    public function hasActiveSubscription(): bool
    {
        return app(SubscriptionManager::class)->hasActiveSubscription($this);
    }

    public function currentPlan(): ?Plan
    {
        return app(SubscriptionManager::class)->currentPlan($this);
    }

    public function subscriptionExpiresAt(): ?Carbon
    {
        return app(SubscriptionManager::class)->expiresAt($this);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Features & Quotas (résolution vers le propriétaire de l'abonnement)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * L'accès à une feature est-il autorisé ? Et le quota suffisant ?
     */
    public function canConsume(string $featureSlug, int $amount = 1): bool
    {
        return app(SubscriptionManager::class)->canConsume($this, $featureSlug, $amount);
    }

    /**
     * Consomme $amount unités d'une feature consumable.
     */
    public function consume(string $featureSlug, int $amount = 1): SubscriptionUsage
    {
        return app(SubscriptionManager::class)->consume($this, $featureSlug, $amount);
    }

    /**
     * Libère $amount unités (ex: suppression d'un employé → libère un slot).
     */
    public function release(string $featureSlug, int $amount = 1): SubscriptionUsage
    {
        return app(SubscriptionManager::class)->release($this, $featureSlug, $amount);
    }

    /**
     * Solde restant d'une feature consumable.
     * PHP_INT_MAX si illimité.
     */
    public function balance(string $featureSlug): int
    {
        return app(SubscriptionManager::class)->balance($this, $featureSlug);
    }

    /**
     * Charges totales allouées par le plan.
     */
    public function totalCharges(string $featureSlug): int
    {
        return app(SubscriptionManager::class)->totalCharges($this, $featureSlug);
    }

    /**
     * Quantité consommée sur la période en cours.
     */
    public function usedCharges(string $featureSlug): int
    {
        return app(SubscriptionManager::class)->usedCharges($this, $featureSlug);
    }
}
