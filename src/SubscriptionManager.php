<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription;

use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Vnuswilliams\Subscription\Exceptions\SubscriptionManagementNotAllowedException;
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
    protected static ?Closure $subjectResolver = null;

    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly FeatureService $features,
    ) {
    }

    /**
     * Permet à l'application hôte de définir comment résoudre
     * le "vrai" porteur d'abonnement pour un modèle donné.
     */
    public static function resolveSubjectUsing(Closure $resolver): void
    {
        static::$subjectResolver = $resolver;
    }

    /**
     * Reset the resolver (useful for tests).
     */
    public static function flushSubjectResolver(): void
    {
        static::$subjectResolver = null;
    }

    /**
     * Indique si ce modèle est autorisé à administrer l'abonnement qu'il utilise.
     *
     * Sans resolver, le modèle est son propre propriétaire. Lorsqu'un resolver
     * renvoie le propriétaire d'une team, seul ce propriétaire peut effectuer
     * les opérations d'écriture sur l'abonnement mutualisé.
     */
    public static function canManageSubscriptionFor(Model $subscriber): bool
    {
        return static::resolveSubjectFor($subscriber)->is($subscriber);
    }

    /**
     * @throws SubscriptionManagementNotAllowedException
     */
    public static function ensureCanManageSubscriptionFor(Model $subscriber): void
    {
        if (static::canManageSubscriptionFor($subscriber)) {
            return;
        }

        throw SubscriptionManagementNotAllowedException::forSubscriber(
            $subscriber->getMorphClass(),
            $subscriber->getKey() ?? 'unsaved',
        );
    }

    /**
     * Résout le sujet effectif porteur d'abonnement.
     * Si aucun resolver n'est configuré, retourne le modèle tel quel.
     */
    protected static function resolveSubjectFor(Model $model): Model
    {
        return static::$subjectResolver
            ? (static::$subjectResolver)($model)
            : $model;
    }

    public function canManageSubscription(Model $subscriber): bool
    {
        return static::canManageSubscriptionFor($subscriber);
    }

    protected function resolveSubject(Model $model): Model
    {
        return static::resolveSubjectFor($model);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Souscription / cycle de vie (WRITE — propriétaire uniquement)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @throws SubscriptionManagementNotAllowedException
     */
    public function subscribeTo(Model $subscriber, string|Plan $plan, ?Carbon $expiration = null, bool $immediately = true, int|float|string|null $price = null): Subscription
    {
        return $this->subscriptions->subscribeTo($subscriber, $plan, $expiration, $immediately, $price);
    }

    /**
     * @throws SubscriptionManagementNotAllowedException
     */
    public function switchTo(Model $subscriber, string|Plan $plan, bool $immediately = true, int|float|string|null $price = null): Subscription
    {
        return $this->subscriptions->switchTo($subscriber, $plan, $immediately, $price);
    }

    /**
     * @throws SubscriptionManagementNotAllowedException
     */
    public function renew(Model $subscriber): Subscription
    {
        return $this->subscriptions->renew($subscriber);
    }

    /**
     * @throws SubscriptionManagementNotAllowedException
     */
    public function cancel(Model $subscriber): Subscription
    {
        return $this->subscriptions->cancel($subscriber);
    }

    /**
     * @throws SubscriptionManagementNotAllowedException
     */
    public function suppress(Model $subscriber): Subscription
    {
        return $this->subscriptions->suppress($subscriber);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  État de l'abonnement (READ — résolution du sujet)
    // ─────────────────────────────────────────────────────────────────────────

    public function hasActiveSubscription(Model $subscriber): bool
    {
        $subscriber = $this->resolveSubject($subscriber);

        return $this->subscriptions->hasActiveSubscription($subscriber);
    }

    public function currentPlan(Model $subscriber): ?Plan
    {
        $subscriber = $this->resolveSubject($subscriber);

        return $this->subscriptions->currentPlan($subscriber);
    }

    public function expiresAt(Model $subscriber): ?Carbon
    {
        $subscriber = $this->resolveSubject($subscriber);

        return $this->subscriptions->expiresAt($subscriber);
    }

    public function resolvePlan(string|Plan $plan): Plan
    {
        return $this->subscriptions->resolvePlan($plan);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Features & Quotas (READ + CONSUME — résolution du sujet)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Vérifie si le subscriber peut consommer $amount unités de $featureSlug.
     * Pour une feature booléenne, $amount est ignoré.
     */
    public function canConsume(Model $subscriber, string $featureSlug, int $amount = 1): bool
    {
        $subscriber = $this->resolveSubject($subscriber);

        return $this->features->canConsume($subscriber, $featureSlug, $amount);
    }

    /**
     * Consomme $amount unités d'une feature consumable.
     */
    public function consume(Model $subscriber, string $featureSlug, int $amount = 1): SubscriptionUsage
    {
        $subscriber = $this->resolveSubject($subscriber);

        return $this->features->consume($subscriber, $featureSlug, $amount);
    }

    /**
     * Libère $amount unités (ex: suppression d'un employé).
     */
    public function release(Model $subscriber, string $featureSlug, int $amount = 1): SubscriptionUsage
    {
        $subscriber = $this->resolveSubject($subscriber);

        return $this->features->release($subscriber, $featureSlug, $amount);
    }

    /**
     * Solde restant. PHP_INT_MAX si illimité.
     */
    public function balance(Model $subscriber, string $featureSlug): int
    {
        $subscriber = $this->resolveSubject($subscriber);

        return $this->features->balance($subscriber, $featureSlug);
    }

    /**
     * Total des charges allouées par le plan.
     */
    public function totalCharges(Model $subscriber, string $featureSlug): int
    {
        $subscriber = $this->resolveSubject($subscriber);

        return $this->features->totalCharges($subscriber, $featureSlug);
    }

    /**
     * Quantité consommée sur la période en cours.
     */
    public function usedCharges(Model $subscriber, string $featureSlug): int
    {
        $subscriber = $this->resolveSubject($subscriber);

        return $this->features->usedCharges($subscriber, $featureSlug);
    }
}
