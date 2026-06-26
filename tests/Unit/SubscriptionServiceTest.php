<?php

declare(strict_types=1);

use Vnuswilliams\Subscription\Enums\SubscriptionStatus;
use Vnuswilliams\Subscription\Models\Plan;
use Vnuswilliams\Subscription\Services\SubscriptionService;
use Vnuswilliams\Subscription\Tests\FakeSubscriber;
use Vnuswilliams\Subscription\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    // Table fake_subscribers pour les tests
    \Illuminate\Support\Facades\Schema::create('fake_subscribers', function ($table): void {
        $table->id();
        $table->timestamps();
    });

    $this->plan = Plan::create([
        'name'             => 'Pro',
        'slug'             => 'pro',
        'periodicity_type' => 'month',
        'periodicity'      => 1,
        'trial_days'       => 0,
        'grace_days'       => 7,
        'is_active'        => true,
    ]);

    $this->subscriber = FakeSubscriber::create([]);
    $this->service    = app(SubscriptionService::class);
});

it('creates a subscription with active status', function (): void {
    $sub = $this->service->subscribeTo($this->subscriber, $this->plan);

    expect($sub->status)->toBe(SubscriptionStatus::Active->value)
        ->and($sub->plan_id)->toBe($this->plan->id);
});

it('sets trial status when plan has trial days', function (): void {
    $this->plan->update(['trial_days' => 14]);

    $sub = $this->service->subscribeTo($this->subscriber, $this->plan);

    expect($sub->status)->toBe(SubscriptionStatus::OnTrial->value)
        ->and($sub->trial_ends_at)->not->toBeNull();
});

it('resolves plan by slug', function (): void {
    $resolved = $this->service->resolvePlan('pro');

    expect($resolved->id)->toBe($this->plan->id);
});

it('throws InvalidPlanException for unknown slug', function (): void {
    $this->service->resolvePlan('unknown-plan');
})->throws(\Vnuswilliams\Subscription\Exceptions\InvalidPlanException::class);

it('cancels a subscription', function (): void {
    $this->service->subscribeTo($this->subscriber, $this->plan);

    $sub = $this->service->cancel($this->subscriber);

    expect($sub->canceled_at)->not->toBeNull()
        ->and($sub->status)->toBe(SubscriptionStatus::Canceled->value);
});

it('suppresses a subscription immediately', function (): void {
    $this->service->subscribeTo($this->subscriber, $this->plan);

    $sub = $this->service->suppress($this->subscriber);

    expect($sub->suppressed_at)->not->toBeNull()
        ->and($sub->status)->toBe(SubscriptionStatus::Expired->value);
});

it('returns false for hasActiveSubscription when none exists', function (): void {
    expect($this->service->hasActiveSubscription($this->subscriber))->toBeFalse();
});

it('returns true for hasActiveSubscription after subscribing', function (): void {
    $this->service->subscribeTo($this->subscriber, $this->plan);

    expect($this->service->hasActiveSubscription($this->subscriber))->toBeTrue();
});
