<?php

declare(strict_types=1);

use Vnuswilliams\Subscription\Enums\FeatureType;
use Vnuswilliams\Subscription\Models\Plan;
use Vnuswilliams\Subscription\Services\FeatureService;
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
        'grace_days'       => 0,
        'is_active'        => true,
    ]);

    $this->plan->features()->create([
        'slug'    => 'max-employees',
        'name'    => 'Max Employees',
        'type'    => FeatureType::Consumable->value,
        'charges' => 10,
    ]);

    $this->plan->features()->create([
        'slug'    => 'export-pdf',
        'name'    => 'Export PDF',
        'type'    => FeatureType::Boolean->value,
        'charges' => null,
    ]);

    $this->subscriber = FakeSubscriber::create([]);

    app(SubscriptionService::class)->subscribeTo($this->subscriber, $this->plan);

    $this->service = app(FeatureService::class);
});

it('allows access to a boolean feature', function (): void {
    expect($this->service->canConsume($this->subscriber, 'export-pdf'))->toBeTrue();
});

it('allows consuming a consumable feature when balance is sufficient', function (): void {
    expect($this->service->canConsume($this->subscriber, 'max-employees', 1))->toBeTrue();
});

it('tracks balance after consume', function (): void {
    $this->service->consume($this->subscriber, 'max-employees', 3);

    expect($this->service->balance($this->subscriber, 'max-employees'))->toBe(7);
});

it('denies consuming when quota is exhausted', function (): void {
    $this->service->consume($this->subscriber, 'max-employees', 10);

    expect($this->service->canConsume($this->subscriber, 'max-employees', 1))->toBeFalse();
});

it('releases units back to the balance', function (): void {
    $this->service->consume($this->subscriber, 'max-employees', 5);
    $this->service->release($this->subscriber, 'max-employees', 2);

    expect($this->service->balance($this->subscriber, 'max-employees'))->toBe(7);
});

it('returns total charges for a feature', function (): void {
    expect($this->service->totalCharges($this->subscriber, 'max-employees'))->toBe(10);
});

it('returns used charges for a feature', function (): void {
    $this->service->consume($this->subscriber, 'max-employees', 4);

    expect($this->service->usedCharges($this->subscriber, 'max-employees'))->toBe(4);
});

it('throws FeatureNotFoundException when consuming a boolean feature', function (): void {
    $this->service->consume($this->subscriber, 'export-pdf');
})->throws(\Vnuswilliams\Subscription\Exceptions\FeatureNotFoundException::class);
