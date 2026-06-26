<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Enums;

enum SubscriptionStatus: string
{
    case Active          = 'active';
    case Canceled        = 'canceled';
    case Expired         = 'expired';
    case OnGracePeriod   = 'on_grace_period';
    case OnTrial         = 'on_trial';

    public function label(): string
    {
        return match ($this) {
            self::Active        => 'Actif',
            self::Canceled      => 'Annulé',
            self::Expired       => 'Expiré',
            self::OnGracePeriod => 'En période de grâce',
            self::OnTrial       => 'En essai',
        };
    }

    public function isAccessible(): bool
    {
        return match ($this) {
            self::Active, self::OnTrial, self::OnGracePeriod, self::Canceled => true,
            self::Expired => false,
        };
    }
}
