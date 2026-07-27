---
name: laravel-subscription
description: Use when working with the vnuswilliams/laravel-subscription package — installing, configuring, adding plans, managing subscriptions, features/quotas, team support with resolveSubjectUsing, Blade directives, testing, or debugging subscription-related code. Triggers on keywords like subscription, plan, quota, feature, subscribeTo, canConsume, balance, grace period, trial.
---

# laravel-subscription

Skill for the `vnuswilliams/laravel-subscription` Laravel package (v2.0.1+).

## Package overview

A payment-agnostic Laravel subscription package. Handles plans, lifecycle (trial, grace, cancel), consumable feature quotas, team multi-tenant support via a subject resolver, and Blade directives. Does NOT handle payments — you plug in Stripe/Paystack/etc around it.

## Architecture

```
SubscriptionManager          ← Public entry point (Facade or injection)
    │
    ├── SubscriptionService  ← Logic: subscribeTo, cancel, switchTo, renew
    └── FeatureService       ← Logic: canConsume, consume, release, balance

HasSubscriptions (Trait)     ← Proxy on the Eloquent model (no business logic)
```

**Golden rule:** The `HasSubscriptions` trait contains zero business logic. It delegates everything to `SubscriptionManager`. All logic lives in `SubscriptionManager` → `SubscriptionService` / `FeatureService`.

## Key files

| File | Purpose |
|------|---------|
| `src/SubscriptionManager.php` | Public API. Facade target. Has subject resolver. |
| `src/Services/SubscriptionService.php` | Subscription CRUD + lifecycle |
| `src/Services/FeatureService.php` | Quotas: canConsume, consume, release, balance |
| `src/Traits/HasSubscriptions.php` | Model trait — proxy to Manager |
| `src/Models/Plan.php` | Plan model |
| `src/Models/Subscription.php` | Subscription model with status checks |
| `src/Models/PlanFeature.php` | Feature attached to a plan |
| `src/Models/SubscriptionUsage.php` | Usage tracking per subscription+feature |
| `src/SubscriptionServiceProvider.php` | Registers services, middleware, Blade directives |
| `src/Facades/Subscription.php` | Laravel facade |
| `config/subscriptions.php` | Tables, models, price precision, middleware alias |
| `database/migrations/` | 4 migrations: plans, plan_features, subscriptions, subscription_usages |

## Three entry points

### 1. Trait (on the model)
```php
$company->subscribeTo('pro');
$company->hasActiveSubscription();
$company->canConsume('max-employees', 1);
$company->balance('max-employees');
```

### 2. Facade
```php
use Vnuswilliams\Subscription\Facades\Subscription;

Subscription::subscribeTo($company, 'pro');
Subscription::canConsume($company, 'max-employees', 1);
```

### 3. Manager injection (recommended for services)
```php
use Vnuswilliams\Subscription\SubscriptionManager;

class MyService {
    public function __construct(private readonly SubscriptionManager $subscription) {}
}
```

## Subscription lifecycle

```
[on_trial] ──(trial_ends_at passed)──> [active]
[active]   ──(ends_at passed)────────> [on_grace_period] ──(grace_ends_at passed)──> [expired]
[active]   ──(cancel())───────────────> [canceled] (hasAccess() = true until ends_at)
[active]   ──(suppress())─────────────> [expired]  (hasAccess() = false immediately)
```

`hasAccess()` returns true for: active, on_trial, on_grace_period, canceled (if ends_at future). Returns false for: expired, suppressed.

## READ vs WRITE methods (critical distinction)

### READ methods (subject-resolved in v2+)
These resolve through `resolveSubject()` when a resolver is configured:
- `hasActiveSubscription()`
- `currentPlan()`
- `expiresAt()`
- `canConsume()`
- `consume()`
- `release()`
- `balance()`
- `totalCharges()`
- `usedCharges()`

### WRITE methods (never resolved — always operate on the explicit model)
- `subscribeTo()`
- `switchTo()`
- `renew()`
- `cancel()`
- `suppress()`

## Team / Multi-tenant support (v2.0+)

### Subject resolver
Register once in `AppServiceProvider::boot()`:
```php
use Illuminate\Database\Eloquent\Model;
use Vnuswilliams\Subscription\SubscriptionManager;

SubscriptionManager::resolveSubjectUsing(function (Model $model) {
    return $model instanceof User
        ? ($model->team?->owner ?? $model)
        : $model;
});
```

**Rules:**
- No interface or method required on the model
- Read operations delegate to the resolved subject (owner)
- Write operations stay on the explicit model (never resolved)
- Without a resolver, behavior is identical to v1 (full backward compat)
- All team members share the same quota pool
- A member's personal subscription is invisible while they're a non-owner member

### Key API
```php
SubscriptionManager::resolveSubjectUsing(Closure $resolver): void  // Register
SubscriptionManager::flushSubjectResolver(): void                   // Reset (tests)
```

## Blade directives (v2.0+)

Registered automatically by the ServiceProvider. All default to `Auth::user()` when no subscriber is passed.

| Directive | Parameters | Equivalent |
|---|---|---|
| `@hasSubscription` | `(?Model $subscriber)` | `hasActiveSubscription()` |
| `@canConsume($feature, $amount)` | `(string, int, ?Model)` | `canConsume()` |
| `@subscribedTo($slug)` | `(string, ?Model)` | `currentPlan()->slug === $slug` |
| `@onTrial` | `(?Model)` | `subscription->isOnTrial()` |
| `@onGracePeriod` | `(?Model)` | `subscription->isOnGracePeriod()` |
| `@subscriptionCanceled` | `(?Model)` | `subscription->isCanceled()` |
| `@subscriptionExpired` | `(?Model)` | `subscription->isExpired()` |

Example:
```blade
@hasSubscription
    <p>You have access.</p>
@else
    <p>Subscribe to continue.</p>
@endhasSubscription

@canConsume('max-employees')
    <button>Add Employee</button>
@endcanConsume

@subscribedTo('pro')
    <span class="badge">Pro Plan</span>
@endsubscribedTo
```

## Features & Quotas

### Boolean features
```php
if ($company->canConsume('employee-portal')) { /* granted */ }
```
`$amount` is ignored for boolean features.

### Consumable features (check → act → consume pattern)
```php
if ($company->canConsume('max-employees', 1)) {
    $employee = Employee::create([...]);
    $company->consume('max-employees', 1);
}
```

### Release slots
```php
$employee->delete();
$company->release('max-employees', 1);
```

### Inspect quotas
```php
$total   = $company->totalCharges('max-employees');  // e.g. 25
$used    = $company->usedCharges('max-employees');    // e.g. 17
$balance = $company->balance('max-employees');        // e.g. 8 (PHP_INT_MAX if unlimited)
```

## Config
```php
// config/subscriptions.php
'tables' => ['plans', 'plan_features', 'subscriptions', 'subscription_usages'],
'models' => [Plan::class, PlanFeature::class, Subscription::class, SubscriptionUsage::class],
'subscriber_key_type' => 'id', // 'id', 'uuid', 'ulid'
'price' => ['precision' => 12, 'scale' => 2],
'middleware' => ['alias' => 'subscribed'],
```

## Middleware
```php
Route::middleware(['auth', 'subscribed'])->group(fn () => /* ... */);
Route::middleware(['auth', 'subscribed:premium'])->group(fn () => /* ... */);
```

## Events
| Event | When |
|-------|------|
| `SubscriptionCreated` | New subscription |
| `SubscriptionCanceled` | End-of-period cancellation |
| `SubscriptionEnteredGracePeriod` | Entered grace period |
| `SubscriptionExpired` | Grace over, access cut |
| `FeatureQuotaReached` | Feature quota exhausted |

## Artisan command
```bash
php artisan subscription:check-lifecycle
```
Transitions subscriptions through lifecycle. Schedule daily.

## Enums
```php
SubscriptionStatus::Active | OnTrial | OnGracePeriod | Canceled | Expired
FeatureType::Boolean | Consumable
PeriodicityType::Day | Week | Month | Year
```

## Testing
```bash
vendor/bin/pest              # Run all tests
vendor/bin/pest tests/Unit/  # Unit tests only
```

Tests use `orchestra/testbench` with SQLite in-memory. The test subscriber (`FakeSubscriber`) uses the `HasSubscriptions` trait and implements `Authenticatable`.

## Common patterns

### Custom price
```php
$company->subscribeTo('pro', price: 24.99);
```

### Custom expiration
```php
$company->subscribeTo('free', expiration: now()->addDays(15));
```

### Check plan in Blade
```blade
@subscribedTo('enterprise')
    <p>Enterprise features available.</p>
@endsubscribedTo
```

### Admin page showing team subscription
```blade
@hasSubscription($team->owner)
    <p>Team subscription active until {{ $team->owner->subscription->ends_at }}</p>
@endhasSubscription
```
