<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
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
        'grace_days'       => 0,
        'is_active'        => true,
    ]);

    $this->subscriber = FakeSubscriber::create([]);
});

it('returns 403 json for unauthenticated api request', function (): void {
    Route::middleware('subscribed')->get('/test-sub', fn () => response()->json(['ok' => true]));

    $this->getJson('/test-sub')->assertStatus(403);
});

it('allows access when subscriber has active subscription', function (): void {
    app(SubscriptionService::class)->subscribeTo($this->subscriber, $this->plan);

    Route::middleware('subscribed')->get('/test-sub', fn () => response()->json(['ok' => true]));

    $this->actingAs($this->subscriber)->getJson('/test-sub')->assertOk();
});

it('denies access for wrong plan slug', function (): void {
    app(SubscriptionService::class)->subscribeTo($this->subscriber, $this->plan);

    Route::middleware('subscribed:enterprise')->get('/test-ent', fn () => response()->json(['ok' => true]));

    $this->actingAs($this->subscriber)->getJson('/test-ent')->assertStatus(403);
});
