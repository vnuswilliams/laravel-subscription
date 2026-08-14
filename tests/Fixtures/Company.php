<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Vnuswilliams\Subscription\Traits\HasSubscriptions;

final class Company extends Model
{
    use HasSubscriptions;

    protected $table = 'companies';

    /** @var list<string> */
    protected $guarded = [];
}
