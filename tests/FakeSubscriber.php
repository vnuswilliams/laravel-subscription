<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Vnuswilliams\Subscription\Traits\HasSubscriptions;

/**
 * Modèle subscriber minimal utilisé dans les tests.
 */
final class FakeSubscriber extends Model implements Authenticatable
{
    use HasSubscriptions;

    protected $table = 'fake_subscribers';

    /** @var list<string> */
    protected $guarded = [];

    public function getAuthIdentifierName(): string
    {
        return $this->getKeyName();
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
