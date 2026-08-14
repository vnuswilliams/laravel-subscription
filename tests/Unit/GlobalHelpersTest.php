<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Vnuswilliams\Subscription\Contracts\SubscriberResolver;
use Vnuswilliams\Subscription\Exceptions\InvalidSubscriberException;
use Vnuswilliams\Subscription\Models\Plan;
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
        'price' => 19.99,
        'periodicity_type' => 'month',
        'periodicity' => 1,
        'trial_days' => 0,
        'grace_days' => 0,
        'is_active' => true,
    ]);

    $this->plan->features()->create([
        'slug' => 'max-employees',
        'name' => 'Max Employees',
        'type' => 'consumable',
        'charges' => 10,
    ]);

    $this->subscriber = FakeSubscriber::create([]);
    $this->otherSubscriber = FakeSubscriber::create([]);
});

afterEach(function (): void {
    Auth::logout();
});

it('resolves the authenticated subscriber by default', function (): void {
    $this->actingAs($this->subscriber);

    expect(app(SubscriberResolver::class)->resolve()?->is($this->subscriber))->toBeTrue();
});

it('delegates global helpers to the authenticated subscriber', function (): void {
    $this->actingAs($this->subscriber);

    subscribeTo($this->plan);
    consume('max-employees', 3);

    expect(hasActiveSubscription())->toBeTrue()
        ->and(currentPlan()?->slug)->toBe('pro')
        ->and(subscriptionExpiresAt())->not->toBeNull()
        ->and(canConsume('max-employees'))->toBeTrue()
        ->and(balance('max-employees'))->toBe(7)
        ->and(totalCharges('max-employees'))->toBe(10)
        ->and(usedCharges('max-employees'))->toBe(3);

    release('max-employees', 1);

    expect(balance('max-employees'))->toBe(8)
        ->and(usedCharges('max-employees'))->toBe(2);
});

it('delegates switch and renewal helpers to the resolved subscriber', function (): void {
    $this->actingAs($this->subscriber);

    subscribeTo($this->plan);
    $switched = switchTo('pro');
    $renewed = renewSubscription();

    expect($switched)->toBeInstanceOf(\Vnuswilliams\Subscription\Models\Subscription::class)
        ->and($renewed)->toBeInstanceOf(\Vnuswilliams\Subscription\Models\Subscription::class)
        ->and(currentPlan()?->slug)->toBe('pro');
});

it('supports an explicit subscriber to bypass the resolver', function (): void {
    $this->actingAs($this->subscriber);

    subscribeTo($this->plan, subscriber: $this->otherSubscriber);

    expect(hasActiveSubscription($this->otherSubscriber))->toBeTrue()
        ->and(currentPlan($this->otherSubscriber)?->slug)->toBe('pro')
        ->and(hasActiveSubscription($this->subscriber))->toBeFalse();
});

it('allows the application to override the resolver binding', function (): void {
    app()->bind(SubscriberResolver::class, function () {
        return new class implements SubscriberResolver
        {
            public function resolve(): ?Model
            {
                return FakeSubscriber::query()->find(2);
            }
        };
    });

    subscribeTo($this->plan);

    expect(hasActiveSubscription($this->otherSubscriber))->toBeTrue()
        ->and(hasActiveSubscription($this->subscriber))->toBeFalse();
});

it('throws an explicit exception when no subscriber is available', function (): void {
    Auth::logout();

    expect(fn () => currentPlan())
        ->toThrow(InvalidSubscriberException::class);
});

it('throws an explicit exception when the resolved model lacks HasSubscriptions', function (): void {
    app()->bind(SubscriberResolver::class, function () {
        return new class implements SubscriberResolver
        {
            public function resolve(): ?Model
            {
                return new class extends Model
                {
                };
            }
        };
    });

    expect(fn () => currentPlan())
        ->toThrow(InvalidSubscriberException::class);
});
