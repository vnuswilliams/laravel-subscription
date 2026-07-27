<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Vnuswilliams\Subscription\Enums\FeatureType;
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
        'trial_days'       => 14,
        'grace_days'       => 7,
        'is_active'        => true,
    ]);

    $this->plan->features()->create([
        'slug'    => 'max-employees',
        'name'    => 'Max Employees',
        'type'    => FeatureType::Consumable->value,
        'charges' => 10,
    ]);

    $this->subscriber = FakeSubscriber::create([]);
    $this->manager    = app(SubscriptionManager::class);
    $this->service    = app(SubscriptionService::class);
});

afterEach(function (): void {
    SubscriptionManager::flushSubjectResolver();
});

/**
 * Compile a Blade string and evaluate it with the given subscriber as Auth user.
 */
function evalBlade(string $template, ?FakeSubscriber $user = null): string
{
    Auth::shouldReceive('user')->andReturn($user);

    $compiled = Blade::compileString($template);

    ob_start();

    eval('?>' . $compiled);

    return trim(ob_get_clean() ?: '');
}

// ─── @hasSubscription ───────────────────────────────────────────────────

it('hasSubscription directive returns true when user has active subscription', function (): void {
    $this->service->subscribeTo($this->subscriber, $this->plan);

    $result = evalBlade("@hasSubscription\nyes\n@else\nno\n@endhasSubscription", $this->subscriber);

    expect($result)->toBe('yes');
});

it('hasSubscription directive returns false when user has no subscription', function (): void {
    $result = evalBlade("@hasSubscription\nyes\n@else\nno\n@endhasSubscription", $this->subscriber);

    expect($result)->toBe('no');
});

// ─── @canConsume ────────────────────────────────────────────────────────

it('canConsume directive returns true when quota is available', function (): void {
    $this->service->subscribeTo($this->subscriber, $this->plan);

    $result = evalBlade("@canConsume('max-employees')\nyes\n@else\nno\n@endcanConsume", $this->subscriber);

    expect($result)->toBe('yes');
});

it('canConsume directive returns false when no subscription', function (): void {
    $result = evalBlade("@canConsume('max-employees')\nyes\n@else\nno\n@endcanConsume", $this->subscriber);

    expect($result)->toBe('no');
});

// ─── @subscribedTo ──────────────────────────────────────────────────────

it('subscribedTo directive returns true for matching plan slug', function (): void {
    $this->service->subscribeTo($this->subscriber, $this->plan);

    $result = evalBlade("@subscribedTo('pro')\nyes\n@else\nno\n@endsubscribedTo", $this->subscriber);

    expect($result)->toBe('yes');
});

it('subscribedTo directive returns false for non-matching plan slug', function (): void {
    $this->service->subscribeTo($this->subscriber, $this->plan);

    $result = evalBlade("@subscribedTo('enterprise')\nyes\n@else\nno\n@endsubscribedTo", $this->subscriber);

    expect($result)->toBe('no');
});

// ─── @onTrial ───────────────────────────────────────────────────────────

it('onTrial directive returns true when subscription is on trial', function (): void {
    $this->service->subscribeTo($this->subscriber, $this->plan);

    $result = evalBlade("@onTrial\nyes\n@else\nno\n@endonTrial", $this->subscriber);

    expect($result)->toBe('yes');
});

it('onTrial directive returns false when subscription is not on trial', function (): void {
    $this->plan->update(['trial_days' => 0]);
    $this->service->subscribeTo($this->subscriber, $this->plan);

    $result = evalBlade("@onTrial\nyes\n@else\nno\n@endonTrial", $this->subscriber);

    expect($result)->toBe('no');
});

// ─── @onGracePeriod ─────────────────────────────────────────────────────

it('onGracePeriod directive returns false when not in grace period', function (): void {
    $this->service->subscribeTo($this->subscriber, $this->plan);

    $result = evalBlade("@onGracePeriod\nyes\n@else\nno\n@endonGracePeriod", $this->subscriber);

    expect($result)->toBe('no');
});

// ─── @subscriptionCanceled ──────────────────────────────────────────────

it('subscriptionCanceled directive returns true when subscription is canceled', function (): void {
    $this->service->subscribeTo($this->subscriber, $this->plan);
    $this->service->cancel($this->subscriber);

    $result = evalBlade("@subscriptionCanceled\nyes\n@else\nno\n@endsubscriptionCanceled", $this->subscriber);

    expect($result)->toBe('yes');
});

it('subscriptionCanceled directive returns false when subscription is active', function (): void {
    $this->service->subscribeTo($this->subscriber, $this->plan);

    $result = evalBlade("@subscriptionCanceled\nyes\n@else\nno\n@endsubscriptionCanceled", $this->subscriber);

    expect($result)->toBe('no');
});

// ─── @subscriptionExpired ───────────────────────────────────────────────

it('subscriptionExpired directive returns false when subscription is active', function (): void {
    $this->service->subscribeTo($this->subscriber, $this->plan);

    $result = evalBlade("@subscriptionExpired\nyes\n@else\nno\n@endsubscriptionExpired", $this->subscriber);

    expect($result)->toBe('no');
});

// ─── Blade directives respect the subject resolver ──────────────────────

it('hasSubscription directive uses the subject resolver', function (): void {
    $this->service->subscribeTo($this->subscriber, $this->plan);

    $otherSubscriber = FakeSubscriber::create([]);
    $subscriber = $this->subscriber;

    SubscriptionManager::resolveSubjectUsing(function (Model $model) use ($otherSubscriber, $subscriber) {
        return $model instanceof FakeSubscriber && $model->id === $otherSubscriber->id
            ? $subscriber
            : $model;
    });

    $result = evalBlade("@hasSubscription\nyes\n@else\nno\n@endhasSubscription", $otherSubscriber);

    expect($result)->toBe('yes');
});
