<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Contracts;

use Illuminate\Database\Eloquent\Model;

interface SubscriberResolver
{
    public function resolve(): ?Model;
}
