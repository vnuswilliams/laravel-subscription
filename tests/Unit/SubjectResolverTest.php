<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Vnuswilliams\Subscription\Enums\FeatureType;
use Vnuswilliams\Subscription\Enums\SubscriptionStatus;
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
        'name'             => 'Pro',
        'slug'             => 'pro',
        'periodicity_type' => 'month',
        'periodicity'      => 1,
        'price'            => 19.99,
        'trial_days'       => 0,
        'grace_days'       => 7,
        'is_active'        => true,
    ]);

    $this->plan->features()->create([
        'slug'    => 'max-employees',
        'name'    => 'Max Employees',
        'type'    => FeatureType::Consumable->value,
        'charges' => 10,
    ]);

    $this->owner    = FakeSubscriber::create(['id' => 1]);
    $this->member   = FakeSubscriber::create(['id' => 2]);
    $this->service  = app(SubscriptionService::class);
    $this->manager  = app(SubscriptionManager::class);
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

// ─── Write methods NOT affected by resolver ─────────────────────────────

it('subscribeTo writes on the explicit model, not the resolved subject', function (): void {
    SubscriptionManager::resolveSubjectUsing(function (Model $model) {
        return $model instanceof FakeSubscriber && $model->id === $this->member->id
            ? $this->owner
            : $model;
    });

    $this->manager->subscribeTo($this->member, $this->plan);

    expect($this->member->subscription()->first())->not->toBeNull();
    expect($this->owner->subscription()->first())->toBeNull();

    expect($this->manager->hasActiveSubscription($this->member))->toBeFalse();
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

it('switchTo operates on the explicit model, not the resolved subject', function (): void {
    SubscriptionManager::resolveSubjectUsing(function (Model $model) {
        return $model instanceof FakeSubscriber && $model->id === $this->member->id
            ? $this->owner
            : $model;
    });

    $this->service->subscribeTo($this->member, $this->plan);
    $this->manager->switchTo($this->member, $this->plan);

    expect($this->manager->hasActiveSubscription($this->member))->toBeFalse();
    expect($this->member->subscription()->first())->not->toBeNull();
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
