<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Exceptions;

use RuntimeException;

final class SubscriptionNotFoundException extends RuntimeException
{
    public static function forSubscriber(string $subscriberType, int|string $subscriberId): self
    {
        return new self(
            "No active subscription found for [{$subscriberType}#{$subscriberId}]."
        );
    }
}
