<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Vnuswilliams\Subscription\Enums\FeatureType;
use Vnuswilliams\Subscription\Exceptions\SubscriptionManagementNotAllowedException;
use Vnuswilliams\Subscription\Models\Plan;
use Vnuswilliams\Subscription\Services\SubscriptionService;
use Vnuswilliams\Subscription\SubscriptionManager;
use Vnuswilliams\Subscription\Tests\FakeSubscriber;
use Vnuswilliams\Subscription\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    \Illuminate\Support\Facades\Schema::create('fake_subscribers', function ($table): void {
        $table->id();
        $table->timestamps();
    });

    $this->plan = Plan::create([
        'name' => 'Pro',
        'slug' => 'pro',
        'periodicity_type' => 'month',
        'periodicity' => 1,
        'price' => 19.99,
        'trial_days' => 0,
        'grace_days' => 7,
        'is_active' => true,
    ]);

    $this->plan->features()->create([
        'slug' => 'max-employees',
        'name' => 'Max Employees',
        'type' => FeatureType::Consumable->value,
        'charges' => 10,
    ]);

    $this->owner = FakeSubscriber::create(['id' => 1]);
    $this->member = FakeSubscriber::create(['id' => 2]);
    $this->service = app(SubscriptionService::class);
    $this->manager = app(SubscriptionManager::class);
});

afterEach(function (): void {
    SubscriptionManager::flushSubjectResolver();
});

// ─── Sans resolver : comportement inchangé ───────────────────────────────

it('returns false for hasActiveSubscription when no resolver and no subscription', function (): void {
    expect($this->manager->hasActiveSubscription($this->member))->toBeFalse();
});

it('checks subscription on the model itself when no resolver is set', function (): void {
    $this->service->subscribeTo($this->member, $this->plan);

    expect($this->manager->hasActiveSubscription($this->member))->toBeTrue();
    expect($this->manager->hasActiveSubscription($this->owner))->toBeFalse();
});

// ─── Avec resolver : délégation vers le owner ───────────────────────────

it('delegates hasActiveSubscription to the owner via resolver', function (): void {
    SubscriptionManager::resolveSubjectUsing(function (Model $model) {
        return $model instanceof FakeSubscriber && $model->id === $this->member->id
            ? $this->owner
            : $model;
    });

    $this->service->subscribeTo($this->owner, $this->plan);

    expect($this->manager->hasActiveSubscription($this->member))->toBeTrue();
});

it('delegates currentPlan to the owner via resolver', function (): void {
    SubscriptionManager::resolveSubjectUsing(function (Model $model) {
        return $model instanceof FakeSubscriber && $model->id === $this->member->id
            ? $this->owner
            : $model;
    });

    $this->service->subscribeTo($this->owner, $this->plan);

    $plan = $this->manager->currentPlan($this->member);

    expect($plan)->not->toBeNull()
        ->and($plan->slug)->toBe('pro');
});

it('returns the resolved owner plan through instance and authenticated helpers', function (): void {
    SubscriptionManager::resolveSubjectUsing(function (Model $model) {
        return $model instanceof FakeSubscriber && $model->id === $this->member->id
            ? $this->owner
            : $model;
    });

    $this->service->subscribeTo($this->owner, $this->plan);
    $this->actingAs($this->member);

    expect($this->member->plan()?->slug)->toBe('pro')
        ->and(FakeSubscriber::authenticatedPlan()?->slug)->toBe('pro')
        ->and(FakeSubscriber::authenticatedHasActiveSubscription())->toBeTrue()
        ->and(FakeSubscriber::authenticatedSubscriptionExpiresAt())->not->toBeNull();
});

it('delegates expiresAt to the owner via resolver', function (): void {
    SubscriptionManager::resolveSubjectUsing(function (Model $model) {
        return $model instanceof FakeSubscriber && $model->id === $this->member->id
            ? $this->owner
            : $model;
    });

    $this->service->subscribeTo($this->owner, $this->plan);

    expect($this->manager->expiresAt($this->member))->not->toBeNull();
});

it('delegates canConsume to the owner via resolver', function (): void {
    SubscriptionManager::resolveSubjectUsing(function (Model $model) {
        return $model instanceof FakeSubscriber && $model->id === $this->member->id
            ? $this->owner
            : $model;
    });

    $this->service->subscribeTo($this->owner, $this->plan);

    expect($this->manager->canConsume($this->member, 'max-employees'))->toBeTrue();
});

it('delegates balance to the owner via resolver', function (): void {
    SubscriptionManager::resolveSubjectUsing(function (Model $model) {
        return $model instanceof FakeSubscriber && $model->id === $this->member->id
            ? $this->owner
            : $model;
    });

    $this->service->subscribeTo($this->owner, $this->plan);

    expect($this->manager->balance($this->member, 'max-employees'))->toBe(10);
});

it('delegates totalCharges to the owner via resolver', function (): void {
    SubscriptionManager::resolveSubjectUsing(function (Model $model) {
        return $model instanceof FakeSubscriber && $model->id === $this->member->id
            ? $this->owner
            : $model;
    });

    $this->service->subscribeTo($this->owner, $this->plan);

    expect($this->manager->totalCharges($this->member, 'max-employees'))->toBe(10);
});

it('delegates usedCharges to the owner via resolver', function (): void {
    SubscriptionManager::resolveSubjectUsing(function (Model $model) {
        return $model instanceof FakeSubscriber && $model->id === $this->member->id
            ? $this->owner
            : $model;
    });

    $this->service->subscribeTo($this->owner, $this->plan);
    $this->manager->consume($this->member, 'max-employees', 3);

    expect($this->manager->usedCharges($this->member, 'max-employees'))->toBe(3);
});

it('delegates consume to the owner via resolver', function (): void {
    SubscriptionManager::resolveSubjectUsing(function (Model $model) {
        return $model instanceof FakeSubscriber && $model->id === $this->member->id
            ? $this->owner
            : $model;
    });

    $this->service->subscribeTo($this->owner, $this->plan);
    $this->manager->consume($this->member, 'max-employees', 3);

    expect($this->manager->balance($this->member, 'max-employees'))->toBe(7);
});

it('delegates release to the owner via resolver', function (): void {
    SubscriptionManager::resolveSubjectUsing(function (Model $model) {
        return $model instanceof FakeSubscriber && $model->id === $this->member->id
            ? $this->owner
            : $model;
    });

    $this->service->subscribeTo($this->owner, $this->plan);
    $this->manager->consume($this->member, 'max-employees', 5);
    $this->manager->release($this->member, 'max-employees', 2);

    expect($this->manager->balance($this->member, 'max-employees'))->toBe(7);
});

// ─── Owner résolu vers lui-même ─────────────────────────────────────────

it('resolves owner to itself when owner calls read methods', function (): void {
    SubscriptionManager::resolveSubjectUsing(function (Model $model) {
        return $model instanceof FakeSubscriber && $model->id === $this->member->id
            ? $this->owner
            : $model;
    });

    $this->service->subscribeTo($this->owner, $this->plan);

    expect($this->manager->hasActiveSubscription($this->owner))->toBeTrue()
        ->and($this->manager->currentPlan($this->owner)?->slug)->toBe('pro');
});

// ─── Gestion d’abonnement réservée au propriétaire ───────────────────────

it('identifies only the resolved owner as able to manage the subscription', function (): void {
    SubscriptionManager::resolveSubjectUsing(function (Model $model) {
        return $model instanceof FakeSubscriber && $model->id === $this->member->id
            ? $this->owner
            : $model;
    });

    expect($this->manager->canManageSubscription($this->member))->toBeFalse()
        ->and($this->member->canManageSubscription())->toBeFalse()
        ->and($this->manager->canManageSubscription($this->owner))->toBeTrue()
        ->and($this->owner->canManageSubscription())->toBeTrue();
});

it('prevents a team member from starting a subscription through the manager or trait', function (): void {
    SubscriptionManager::resolveSubjectUsing(function (Model $model) {
        return $model instanceof FakeSubscriber && $model->id === $this->member->id
            ? $this->owner
            : $model;
    });

    expect(fn () => $this->manager->subscribeTo($this->member, $this->plan))
        ->toThrow(SubscriptionManagementNotAllowedException::class)
        ->and(fn () => $this->member->subscribeTo($this->plan))
        ->toThrow(SubscriptionManagementNotAllowedException::class)
        ->and(fn () => $this->service->subscribeTo($this->member, $this->plan))
        ->toThrow(SubscriptionManagementNotAllowedException::class);

    expect($this->member->subscription()->first())->toBeNull()
        ->and($this->owner->subscription()->first())->toBeNull();
});

it('cancel operates on the explicit model, not the resolved subject', function (): void {
    SubscriptionManager::resolveSubjectUsing(function (Model $model) {
        return $model instanceof FakeSubscriber && $model->id === $this->member->id
            ? $this->owner
            : $model;
    });

    $this->service->subscribeTo($this->owner, $this->plan);
    $this->manager->cancel($this->owner);

    expect($this->manager->hasActiveSubscription($this->owner))->toBeFalse();
    expect($this->manager->hasActiveSubscription($this->member))->toBeFalse();
});

it('prevents direct subscription-model transitions for a team member', function (): void {
    SubscriptionManager::resolveSubjectUsing(function (Model $model) {
        return $model instanceof FakeSubscriber && $model->id === $this->member->id
            ? $this->owner
            : $model;
    });

    $legacySubscription = $this->member->subscription()->create([
        'plan_id' => $this->plan->id,
        'price' => $this->plan->price,
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    expect(fn () => $legacySubscription->cancel())
        ->toThrow(SubscriptionManagementNotAllowedException::class)
        ->and(fn () => $legacySubscription->suppress())
        ->toThrow(SubscriptionManagementNotAllowedException::class)
        ->and(fn () => $legacySubscription->renew())
        ->toThrow(SubscriptionManagementNotAllowedException::class);
});

it('prevents a team member from changing, renewing, cancelling, or suppressing a subscription', function (): void {
    SubscriptionManager::resolveSubjectUsing(function (Model $model) {
        return $model instanceof FakeSubscriber && $model->id === $this->member->id
            ? $this->owner
            : $model;
    });

    expect(fn () => $this->manager->switchTo($this->member, $this->plan))
        ->toThrow(SubscriptionManagementNotAllowedException::class)
        ->and(fn () => $this->manager->renew($this->member))
        ->toThrow(SubscriptionManagementNotAllowedException::class)
        ->and(fn () => $this->manager->cancel($this->member))
        ->toThrow(SubscriptionManagementNotAllowedException::class)
        ->and(fn () => $this->manager->suppress($this->member))
        ->toThrow(SubscriptionManagementNotAllowedException::class);
});

// ─── Shared quota pool ──────────────────────────────────────────────────

it('all team members share the same quota pool via the owner', function (): void {
    SubscriptionManager::resolveSubjectUsing(function (Model $model) {
        return $model instanceof FakeSubscriber && $model->id === $this->member->id
            ? $this->owner
            : $model;
    });

    $this->service->subscribeTo($this->owner, $this->plan);

    $this->manager->consume($this->member, 'max-employees', 3);

    expect($this->manager->balance($this->member, 'max-employees'))->toBe(7)
        ->and($this->manager->balance($this->owner, 'max-employees'))->toBe(7)
        ->and($this->manager->usedCharges($this->owner, 'max-employees'))->toBe(3);
});

// ─── Trait HasSubscriptions + owner de team ──────────────────────────────

it('routes trait reads and quota consumption to the team owner', function (): void {
    SubscriptionManager::resolveSubjectUsing(function (Model $model) {
        return $model instanceof FakeSubscriber && $model->id === $this->member->id
            ? $this->owner
            : $model;
    });

    $this->owner->subscribeTo($this->plan);

    expect($this->member->hasActiveSubscription())->toBeTrue()
        ->and($this->member->currentPlan()?->slug)->toBe('pro')
        ->and($this->member->subscriptionExpiresAt())->not->toBeNull()
        ->and($this->member->canConsume('max-employees', 4))->toBeTrue()
        ->and($this->member->totalCharges('max-employees'))->toBe(10)
        ->and($this->member->balance('max-employees'))->toBe(10);

    $usage = $this->member->consume('max-employees', 4);

    expect($usage->subscription_id)->toBe($this->owner->subscription()->firstOrFail()->id)
        ->and($this->member->usedCharges('max-employees'))->toBe(4)
        ->and($this->owner->usedCharges('max-employees'))->toBe(4)
        ->and($this->member->balance('max-employees'))->toBe(6);

    $this->member->release('max-employees', 2);

    expect($this->owner->usedCharges('max-employees'))->toBe(2)
        ->and($this->member->balance('max-employees'))->toBe(8);
});

it('returns the owner plan when the team member has a personal subscription', function (): void {
    SubscriptionManager::resolveSubjectUsing(function (Model $model) {
        return $model instanceof FakeSubscriber && $model->id === $this->member->id
            ? $this->owner
            : $model;
    });

    $memberPlan = Plan::create([
        'name' => 'Personal',
        'slug' => 'personal',
        'periodicity_type' => 'month',
        'periodicity' => 1,
        'price' => 9.99,
        'trial_days' => 0,
        'grace_days' => 7,
        'is_active' => true,
    ]);

    $this->owner->subscribeTo($this->plan);
    $this->member->subscription()->create([
        'plan_id' => $memberPlan->id,
        'price' => $memberPlan->price,
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    $ownerPlan = $this->owner->currentPlan();
    $resolvedPlan = $this->member->currentPlan();

    expect($resolvedPlan)->not->toBeNull()
        ->and($ownerPlan)->not->toBeNull()
        ->and($resolvedPlan->is($ownerPlan))->toBeTrue()
        ->and($resolvedPlan->slug)->toBe('pro');
});

// ─── flushSubjectResolver ───────────────────────────────────────────────

it('flushSubjectResolver removes the resolver and restores default behavior', function (): void {
    SubscriptionManager::resolveSubjectUsing(function (Model $model) {
        return $model instanceof FakeSubscriber && $model->id === $this->member->id
            ? $this->owner
            : $model;
    });

    $this->service->subscribeTo($this->owner, $this->plan);

    expect($this->manager->hasActiveSubscription($this->member))->toBeTrue();

    SubscriptionManager::flushSubjectResolver();

    expect($this->manager->hasActiveSubscription($this->member))->toBeFalse();
});
