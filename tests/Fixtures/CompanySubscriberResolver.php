<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Vnuswilliams\Subscription\Contracts\SubscriberResolver;

final class CompanySubscriberResolver implements SubscriberResolver
{
    public function __construct(
        private readonly int $companyId,
    ) {
    }

    public function resolve(): ?Model
    {
        return Company::query()->find($this->companyId);
    }
}
