<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Tests;

use Illuminate\Database\Eloquent\Model;
use Vnuswilliams\Subscription\Traits\HasSubscriptions;

/**
 * Modèle subscriber minimal utilisé dans les tests.
 */
final class FakeSubscriber extends Model
{
    use HasSubscriptions;

    protected $table = 'fake_subscribers';

    /** @var list<string> */
    protected $guarded = [];
}
