<?php

declare(strict_types=1);

use Carbon\Carbon;
use Vnuswilliams\Subscription\Enums\SubscriptionStatus;
use Vnuswilliams\Subscription\Models\Plan;
use Vnuswilliams\Subscription\Services\SubscriptionService;
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
        'trial_days'       => 0,
        'grace_days'       => 7,
        'is_active'        => true,
    ]);

    $this->subscriber = FakeSubscriber::create([]);
    $this->service    = app(SubscriptionService::class);
});

it('grants access during grace period', function (): void {
    $sub = $this->service->subscribeTo($this->subscriber, $this->plan);
    $sub->update([
        'status'        => SubscriptionStatus::OnGracePeriod->value,
        'ends_at'       => now()->subDay(),
        'grace_ends_at' => now()->addDays(5),
    ]);

    expect($this->service->hasActiveSubscription($this->subscriber))->toBeTrue();
});

it('denies access after grace period ends', function (): void {
    $sub = $this->service->subscribeTo($this->subscriber, $this->plan);
    $sub->update([
        'status'        => SubscriptionStatus::Expired->value,
        'ends_at'       => now()->subDays(10),
        'grace_ends_at' => now()->subDays(3),
        'suppressed_at' => now()->subDays(3),
    ]);

    expect($this->service->hasActiveSubscription($this->subscriber))->toBeFalse();
});

it('grants access to canceled subscription within its period', function (): void {
    $sub = $this->service->subscribeTo($this->subscriber, $this->plan);
    $sub->update([
        'canceled_at' => now(),
        'status'      => SubscriptionStatus::Canceled->value,
        'ends_at'     => now()->addDays(15),
    ]);

    expect($this->service->hasActiveSubscription($this->subscriber))->toBeTrue();
});
