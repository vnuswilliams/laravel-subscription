<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Vnuswilliams\Subscription\Console\Commands\CheckSubscriptionLifecycle;
use Vnuswilliams\Subscription\Http\Middleware\CheckSubscription;
use Vnuswilliams\Subscription\Services\FeatureService;
use Vnuswilliams\Subscription\Services\SubscriptionService;

final class SubscriptionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/subscriptions.php',
            'subscriptions'
        );

        // Services internes (logique métier)
        $this->app->singleton(SubscriptionService::class);
        $this->app->singleton(FeatureService::class);

        // Manager : point d'entrée public du package (Facade + injection)
        $this->app->singleton(SubscriptionManager::class, function ($app): SubscriptionManager {
            return new SubscriptionManager(
                $app->make(SubscriptionService::class),
                $app->make(FeatureService::class),
            );
        });
    }

    public function boot(): void
    {
        $this->publishConfig();
        $this->publishMigrations();
        $this->publishStubs();
        $this->registerMiddleware();
        $this->registerCommands();
        $this->registerBladeDirectives();
    }

    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/subscriptions.php' => config_path('subscriptions.php'),
        ], 'subscription-config');
    }

    private function publishMigrations(): void
    {
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'subscription-migrations');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    private function publishStubs(): void
    {
        $this->publishes([
            __DIR__ . '/../stubs/SubscriptionService.stub' => app_path('Services/SubscriptionService.php'),
        ], 'subscription-stubs');
    }

    private function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $alias = config('subscriptions.middleware.alias', 'subscribed');

        $router->aliasMiddleware($alias, CheckSubscription::class);
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckSubscriptionLifecycle::class,
            ]);
        }
    }

    private function registerBladeDirectives(): void
    {
        Blade::if('hasSubscription', function (?Model $subscriber = null) {
            $subscriber ??= Auth::user();

            return $subscriber && app(SubscriptionManager::class)->hasActiveSubscription($subscriber);
        });

        Blade::if('canConsume', function (string $feature, int $amount = 1, ?Model $subscriber = null) {
            $subscriber ??= Auth::user();

            return $subscriber && app(SubscriptionManager::class)->canConsume($subscriber, $feature, $amount);
        });

        Blade::if('subscribedTo', function (string $planSlug, ?Model $subscriber = null) {
            $subscriber ??= Auth::user();
            $plan = $subscriber ? app(SubscriptionManager::class)->currentPlan($subscriber) : null;

            return $plan?->slug === $planSlug;
        });

        Blade::if('onTrial', function (?Model $subscriber = null) {
            $subscriber ??= Auth::user();

            return $subscriber?->subscription?->isOnTrial() ?? false;
        });

        Blade::if('onGracePeriod', function (?Model $subscriber = null) {
            $subscriber ??= Auth::user();

            return $subscriber?->subscription?->isOnGracePeriod() ?? false;
        });

        Blade::if('subscriptionCanceled', function (?Model $subscriber = null) {
            $subscriber ??= Auth::user();

            return $subscriber?->subscription?->isCanceled() ?? false;
        });

        Blade::if('subscriptionExpired', function (?Model $subscriber = null) {
            $subscriber ??= Auth::user();

            return $subscriber?->subscription?->isExpired() ?? false;
        });
    }
}
