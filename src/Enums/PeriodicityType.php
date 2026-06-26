<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Enums;

/**
 * Type de périodicité d'un plan.
 * Exposé publiquement pour être utilisé dans les PlanEnum de l'application hôte.
 */
enum PeriodicityType: string
{
    case Day   = 'day';
    case Week  = 'week';
    case Month = 'month';
    case Year  = 'year';

    public function addTo(\Carbon\CarbonInterface $date, int $count = 1): \Carbon\CarbonInterface
    {
        return match ($this) {
            self::Day   => $date->addDays($count),
            self::Week  => $date->addWeeks($count),
            self::Month => $date->addMonths($count),
            self::Year  => $date->addYears($count),
        };
    }
}
