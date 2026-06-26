<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Enums;

enum FeatureType: string
{
    /** Accès binaire : oui / non, pas de compteur. */
    case Boolean = 'boolean';

    /** Quota consommable : décrémenté à chaque usage, réinitialisable. */
    case Consumable = 'consumable';
}
