<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Vnuswilliams\Subscription\Enums\SubscriptionStatus;
use Vnuswilliams\Subscription\Events\SubscriptionCreated;
use Vnuswilliams\Subscription\Events\SubscriptionEnteredGracePeriod;
use Vnuswilliams\Subscription\Exceptions\InvalidPlanException;
use Vnuswilliams\Subscription\Exceptions\SubscriptionNotFoundException;
use Vnuswilliams\Subscription\Models\Plan;
use Vnuswilliams\Subscription\Models\Subscription;

final class SubscriptionService
{
    // ─────────────────────────────────────────────────────────────────────────
    //  Souscription / changement de plan
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Souscrit un subscriber à un plan.
     * Gère l'essai si le plan a des trial_days configurés.
     * Si le subscriber a déjà un abonnement actif, effectue un switch.
     *
     * @param  Model  $subscriber   Tout modèle utilisant HasSubscriptions
     * @param  string|Plan  $plan   Slug du plan ou instance Plan
     * @param  Carbon|null  $expiration   Surcharge manuelle de la date de fin
     */
    public function subscribeTo(Model $subscriber, string|Plan $plan, ?Carbon $expiration = null, bool $immediately = true): Subscription
    {
        $plan = $this->resolvePlan($plan);

        /** @var Subscription|null $current */
        $current = $subscriber->subscription()->first(); // @phpstan-ignore-line

        if ($current !== null && $current->hasAccess()) {
            return $this->switchTo($subscriber, $plan, $immediately);
        }

        $now    = now();
        $status = SubscriptionStatus::Active;
        $trialEndsAt = null;

        if ($plan->hasTrial()) {
            $status      = SubscriptionStatus::OnTrial;
            $trialEndsAt = $now->copy()->addDays($plan->trial_days);
        }

        $endsAt = $expiration ?? $plan->expiresAt($now);

        $graceEndsAt = ($endsAt !== null && $plan->hasGrace())
            ? Carbon::parse($endsAt)->addDays($plan->grace_days)
            : null;

        /** @var Subscription $subscription */
        $subscription = $subscriber->subscription()->create([ // @phpstan-ignore-line
            'plan_id'       => $plan->id,
            'status'        => $status->value,
            'trial_ends_at' => $trialEndsAt,
            'starts_at'     => $now,
            'ends_at'       => $endsAt,
            'grace_ends_at' => $graceEndsAt,
        ]);

        event(new SubscriptionCreated($subscription));

        return $subscription;
    }

    /**
     * Change de plan (upgrade / downgrade).
     * Par défaut immédiat : l'ancien abonnement est supprimé, le nouveau commence.
     */
    public function switchTo(Model $subscriber, string|Plan $plan, bool $immediately = true): Subscription
    {
        $plan    = $this->resolvePlan($plan);
        $current = $this->getActiveSubscription($subscriber);

        if ($immediately) {
            $current->suppress();
        } else {
            $current->cancel();
        }

        return $this->subscribeTo($subscriber, $plan);
    }

    /**
     * Renouvelle l'abonnement en cours depuis maintenant.
     */
    public function renew(Model $subscriber): Subscription
    {
        return $this->getActiveSubscription($subscriber)->renew();
    }

    /**
     * Annule l'abonnement : maintien de l'accès jusqu'à ends_at + grace.
     */
    public function cancel(Model $subscriber): Subscription
    {
        return $this->getActiveSubscription($subscriber)->cancel();
    }

    /**
     * Coupe immédiatement l'accès (sans attendre l'expiration).
     */
    public function suppress(Model $subscriber): Subscription
    {
        return $this->getActiveSubscription($subscriber)->suppress();
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Vérifications d'état
    // ─────────────────────────────────────────────────────────────────────────

    public function hasActiveSubscription(Model $subscriber): bool
    {
        $sub = $subscriber->subscription()->first(); // @phpstan-ignore-line

        return $sub instanceof Subscription && $sub->hasAccess();
    }

    public function currentPlan(Model $subscriber): ?Plan
    {
        $sub = $subscriber->subscription()->first(); // @phpstan-ignore-line

        return ($sub instanceof Subscription && $sub->hasAccess())
            ? $sub->plan
            : null;
    }

    public function expiresAt(Model $subscriber): ?Carbon
    {
        $sub = $subscriber->subscription()->first(); // @phpstan-ignore-line

        return ($sub instanceof Subscription && $sub->ends_at !== null)
            ? Carbon::parse($sub->ends_at)
            : null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Lifecycle : entrée en grâce (appelé depuis la commande artisan)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Transition active → on_grace_period si ends_at dépassé et grace disponible.
     * Retourne true si la transition a eu lieu.
     */
    public function transitionToGraceIfNeeded(Subscription $subscription): bool
    {
        if ($subscription->status !== SubscriptionStatus::Active->value) {
            return false;
        }

        if ($subscription->ends_at === null || Carbon::parse($subscription->ends_at)->isFuture()) {
            return false;
        }

        if (! $subscription->plan->hasGrace()) {
            return false;
        }

        $subscription->update(['status' => SubscriptionStatus::OnGracePeriod->value]);

        event(new SubscriptionEnteredGracePeriod($subscription));

        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers privés
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @throws InvalidPlanException
     */
    public function resolvePlan(string|Plan $plan): Plan
    {
        if ($plan instanceof Plan) {
            return $plan;
        }

        $model = Plan::where('slug', $plan)->where('is_active', true)->first();

        if ($model === null) {
            throw InvalidPlanException::notFound($plan);
        }

        return $model;
    }

    /**
     * @throws SubscriptionNotFoundException
     */
    private function getActiveSubscription(Model $subscriber): Subscription
    {
        /** @var Subscription|null $sub */
        $sub = $subscriber->subscription()->first(); // @phpstan-ignore-line

        if (! $sub instanceof Subscription || ! $sub->hasAccess()) {
            throw SubscriptionNotFoundException::forSubscriber(
                $subscriber->getMorphClass(),
                $subscriber->getKey(), // @phpstan-ignore-line
            );
        }

        return $sub;
    }
}
