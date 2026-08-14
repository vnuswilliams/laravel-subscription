<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Vnuswilliams\Subscription\Contracts\SubscriberResolver;

final class AuthSubscriberResolver implements SubscriberResolver
{
    public function resolve(): ?Model
    {
        $subscriber = auth()->user();

        return $subscriber instanceof Model ? $subscriber : null;
    }
}
