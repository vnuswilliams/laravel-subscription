<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Exceptions;

use RuntimeException;

final class SubscriptionManagementNotAllowedException extends RuntimeException
{
    public static function forSubscriber(string $subscriberType, int|string $subscriberId): self
    {
        return new self(
            "Only the subscription owner can manage the subscription for [{$subscriberType}#{$subscriberId}]."
        );
    }
}
