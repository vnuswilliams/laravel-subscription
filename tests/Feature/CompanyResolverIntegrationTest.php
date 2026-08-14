<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Vnuswilliams\Subscription\Contracts\SubscriberResolver;
use Vnuswilliams\Subscription\Models\Plan;
use Vnuswilliams\Subscription\Tests\Fixtures\Company;
use Vnuswilliams\Subscription\Tests\Fixtures\CompanySubscriberResolver;
use Vnuswilliams\Subscription\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Schema::create('companies', function ($table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    $this->plan = Plan::create([
        'name' => 'Business',
        'slug' => 'business',
        'price' => 49.99,
        'periodicity_type' => 'month',
        'periodicity' => 1,
        'trial_days' => 0,
        'grace_days' => 7,
        'is_active' => true,
    ]);

    $this->plan->features()->create([
        'slug' => 'max-employees',
        'name' => 'Max Employees',
        'type' => 'consumable',
        'charges' => 25,
    ]);

    $this->company = Company::create(['name' => 'Acme']);
    $this->otherCompany = Company::create(['name' => 'Globex']);

    app()->bind(
        SubscriberResolver::class,
        fn () => new CompanySubscriberResolver($this->company->getKey()),
    );
});

afterEach(function (): void {
    app()->forgetInstance(SubscriberResolver::class);
});

it('resolves a custom Company model through the application binding', function (): void {
    subscribeTo($this->plan);
    consume('max-employees', 4);

    expect(currentPlan()?->is($this->plan))->toBeTrue()
        ->and(hasActiveSubscription())->toBeTrue()
        ->and(balance('max-employees'))->toBe(21)
        ->and(usedCharges('max-employees'))->toBe(4)
        ->and($this->company->subscription()->first())->not->toBeNull()
        ->and($this->otherCompany->subscription()->first())->toBeNull();
});

it('uses an explicitly supplied Company instead of the resolver result', function (): void {
    subscribeTo($this->plan, subscriber: $this->otherCompany);

    expect(hasActiveSubscription($this->otherCompany))->toBeTrue()
        ->and(currentPlan($this->otherCompany)?->slug)->toBe('business')
        ->and(hasActiveSubscription())->toBeFalse();
});

it('supports company subscription actions without an authenticated user', function (): void {
    subscribeTo($this->plan);
    $renewed = renewSubscription();

    expect($renewed->subscriber->is($this->company))->toBeTrue()
        ->and(subscriptionExpiresAt())->not->toBeNull();
});
