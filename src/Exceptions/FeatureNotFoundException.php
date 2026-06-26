<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Exceptions;

use RuntimeException;

final class FeatureNotFoundException extends RuntimeException
{
    public static function withSlug(string $slug): self
    {
        return new self("Feature [{$slug}] is not attached to the current plan.");
    }
}
