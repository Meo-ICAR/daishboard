<?php

namespace App\Enums;

enum DateRangePreset: string
{
    case Today = 'today';
    case Yesterday = 'yesterday';
    case Last7Days = 'last_7_days';
    case Last30Days = 'last_30_days';
    case Last90Days = 'last_90_days';
    case Last180Days = 'last_180_days';
    case Last365Days = 'last_365_days';
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
            self::Last90Days => 'Ultimi 90 giorni',
            self::Last180Days => 'Ultimi 180 giorni',
            self::Last365Days => 'Ultimi 365 giorni',
            self::ThisWeek => 'Questa settimana',
            self::LastWeek => 'Settimana scorsa',
            self::ThisMonth => 'Questo mese',
            self::LastMonth => 'Mese scorso',
            self::ThisQuarter => 'Questo trimestre',
            self::LastQuarter => 'Trimestre scorso',
            self::ThisYear => 'Anno in corso',
            self::LastYear => 'Anno scorso',
            self::Custom => 'Personalizzato',
        };
    }
}
