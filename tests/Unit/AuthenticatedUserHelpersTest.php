<?php

declare(strict_types=1);

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Vnuswilliams\Subscription\Tests\FakeSubscriber;
use Vnuswilliams\Subscription\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    \Illuminate\Support\Facades\Schema::create('fake_subscribers', function ($table): void {
        $table->id();
        $table->timestamps();
    });

    $this->user = FakeSubscriber::create([]);
    $this->otherUser = FakeSubscriber::create([]);
});

afterEach(function (): void {
    Auth::logout();
});

it('returns the authenticated model through currentUser', function (): void {
    $this->actingAs($this->user);

    expect(FakeSubscriber::currentUser())->not->toBeNull()
        ->and(FakeSubscriber::currentUser()->is($this->user))->toBeTrue();
});

it('returns null through currentUser when unauthenticated', function (): void {
    Auth::logout();

    expect(FakeSubscriber::currentUser())->toBeNull();
});

it('returns the authenticated model through currentUserOrFail', function (): void {
    $this->actingAs($this->user);

    expect(FakeSubscriber::currentUserOrFail()->is($this->user))->toBeTrue();
});

it('throws through currentUserOrFail when unauthenticated', function (): void {
    Auth::logout();

    expect(fn () => FakeSubscriber::currentUserOrFail())
        ->toThrow(AuthenticationException::class);
});

it('checks whether a model is the authenticated user', function (): void {
    $this->actingAs($this->user);

    expect($this->user->isCurrentUser())->toBeTrue()
        ->and($this->otherUser->isCurrentUser())->toBeFalse();
});
