<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Exceptions;

use RuntimeException;

final class InvalidPlanException extends RuntimeException
{
    public static function notFound(string $slug): self
    {
        return new self("No active plan found with slug [{$slug}].");
    }

    public static function inactive(string $slug): self
    {
        return new self("Plan [{$slug}] exists but is not active.");
    }
}
