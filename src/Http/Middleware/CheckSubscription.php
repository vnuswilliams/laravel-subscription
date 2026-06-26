<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Vnuswilliams\Subscription\Services\SubscriptionService;

final class CheckSubscription
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     * @param  string|null  $planSlug  Plan requis (optionnel). ex: middleware('subscribed:premium')
     */
    public function handle(Request $request, Closure $next, ?string $planSlug = null): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->subscriptionService->hasActiveSubscription($user)) {
            return $this->deny($request, 'Aucun abonnement actif.');
        }

        if ($planSlug !== null) {
            $current = $this->subscriptionService->currentPlan($user);

            if ($current === null || $current->slug !== $planSlug) {
                return $this->deny(
                    $request,
                    "Cette fonctionnalité requiert le plan [{$planSlug}]."
                );
            }
        }

        return $next($request);
    }

    private function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_FORBIDDEN);
        }

        return redirect()->route('home')->with('error', $message);
    }
}
