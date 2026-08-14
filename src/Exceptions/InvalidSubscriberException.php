<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Exceptions;

use RuntimeException;

final class InvalidSubscriberException extends RuntimeException
{
    public static function notResolved(): self
    {
        return new self(
            'No subscriber could be resolved. Authenticate a subscriber or pass one explicitly.'
        );
    }

    public static function missingSubscriptionsTrait(string $class): self
    {
        return new self(
            "The resolved subscriber [{$class}] must use "
            .'\\Vnuswilliams\\Subscription\\Traits\\HasSubscriptions.'
        );
    }
}
