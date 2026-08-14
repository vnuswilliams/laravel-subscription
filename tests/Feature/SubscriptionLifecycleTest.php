<?php

declare(strict_types=1);

use Carbon\Carbon;
use Vnuswilliams\Subscription\Enums\SubscriptionStatus;
use Vnuswilliams\Subscription\Events\SubscriptionEnteredGracePeriod;
use Vnuswilliams\Subscription\Events\SubscriptionExpired;
use Vnuswilliams\Subscription\Models\Plan;
use Vnuswilliams\Subscription\Services\SubscriptionService;
use Vnuswilliams\Subscription\Tests\FakeSubscriber;
use Vnuswilliams\Subscription\Tests\TestCase;
use Illuminate\Support\Facades\Event;

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
        'trial_days'       => 0,
        'grace_days'       => 7,
        'is_active'        => true,
    ]);

    $this->subscriber = FakeSubscriber::create([]);
    $this->service    = app(SubscriptionService::class);
});

it('transitions active subscription to grace period when ends_at is past', function (): void {
    Event::fake([SubscriptionEnteredGracePeriod::class]);

    $sub = $this->service->subscribeTo($this->subscriber, $this->plan);
    $sub->update(['ends_at' => now()->subDay()]);

    $transitioned = $this->service->transitionToGraceIfNeeded($sub);

    expect($transitioned)->toBeTrue()
        ->and($sub->fresh()->status)->toBe(SubscriptionStatus::OnGracePeriod->value);

    Event::assertDispatched(SubscriptionEnteredGracePeriod::class);
});

it('does not transition if ends_at is in the future', function (): void {
    $sub = $this->service->subscribeTo($this->subscriber, $this->plan);

    $transitioned = $this->service->transitionToGraceIfNeeded($sub);

    expect($transitioned)->toBeFalse();
});

it('artisan command expires subscriptions past grace', function (): void {
    Event::fake([SubscriptionExpired::class]);

    $sub = $this->service->subscribeTo($this->subscriber, $this->plan);
    $sub->update([
        'ends_at'       => now()->subDays(10),
        'grace_ends_at' => now()->subDays(3),
        'status'        => SubscriptionStatus::OnGracePeriod->value,
    ]);

    $this->artisan('subscription:check-lifecycle')->assertSuccessful();

    expect($sub->fresh()->status)->toBe(SubscriptionStatus::Expired->value);

    Event::assertDispatched(SubscriptionExpired::class);
});
