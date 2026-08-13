<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Vnuswilliams\Subscription\SubscriptionManager;

final class EnsureCanManageSubscription
{
    public function __construct(
        private readonly SubscriptionManager $subscriptions,
    ) {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Model || ! $this->subscriptions->canManageSubscription($user)) {
            return $this->deny($request);
        }

        return $next($request);
    }

    private function deny(Request $request): Response
    {
        $message = 'Seul le propriétaire de la team peut gérer cet abonnement.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_FORBIDDEN);
        }

        return redirect()->route('home')->with('error', $message);
    }
}
