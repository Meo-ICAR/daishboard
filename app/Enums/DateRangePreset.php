<?php

namespace App\Enums;

enum DateRangePreset: string
{
    case Today = 'today';
    case Yesterday = 'yesterday';
    case Last7Days = 'last_7_days';
    case Last30Days = 'last_30_days';
    case ThisWeek = 'this_week';
    case LastWeek = 'last_week';
    case ThisMonth = 'this_month';
    case LastMonth = 'last_month';
    case ThisQuarter = 'this_quarter';
    case LastQuarter = 'last_quarter';
    case ThisYear = 'this_year';
    case LastYear = 'last_year';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Today => 'Oggi',
            self::Yesterday => 'Ieri',
            self::Last7Days => 'Ultimi 7 giorni',
            self::Last30Days => 'Ultimi 30 giorni',
            self::ThisWeek => 'Questa settimana',
            self::LastWeek => 'Settimana scorsa',
            self::ThisMonth => 'Questo mese',
            self::LastMonth => 'Mese scorso',
            self::ThisQuarter => 'Questo trimestre',
            self::LastQuarter => 'Trimestre scorso',
            self::ThisYear => 'Quest\'anno',
            self::LastYear => 'Anno scorso',
            self::Custom => 'Personalizzato',
        };
    }
}
