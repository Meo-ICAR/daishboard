<?php

namespace App\Services;

use App\Enums\DateRangePreset;
use Carbon\Carbon;

class DateRangeResolver
{
    public function resolve(DateRangePreset $preset, ?string $timezone = null): array
    {
        $tz = $timezone ?? config('app.timezone');
        $now = Carbon::now($tz);

        return match ($preset) {
            DateRangePreset::Today => ['from' => $now->copy()->startOfDay(), 'to' => $now->copy()->endOfDay()],
            DateRangePreset::Yesterday => ['from' => $now->copy()->subDay()->startOfDay(), 'to' => $now->copy()->subDay()->endOfDay()],
            DateRangePreset::Last7Days => ['from' => $now->copy()->subDays(6)->startOfDay(), 'to' => $now->copy()->endOfDay()],
            DateRangePreset::Last30Days => ['from' => $now->copy()->subDays(29)->startOfDay(), 'to' => $now->copy()->endOfDay()],
            DateRangePreset::ThisWeek => ['from' => $now->copy()->startOfWeek(), 'to' => $now->copy()->endOfWeek()],
            DateRangePreset::LastWeek => ['from' => $now->copy()->subWeek()->startOfWeek(), 'to' => $now->copy()->subWeek()->endOfWeek()],
            DateRangePreset::ThisMonth => ['from' => $now->copy()->startOfMonth(), 'to' => $now->copy()->endOfMonth()],
            DateRangePreset::LastMonth => ['from' => $now->copy()->subMonth()->startOfMonth(), 'to' => $now->copy()->subMonth()->endOfMonth()],
            DateRangePreset::ThisQuarter => ['from' => $now->copy()->startOfQuarter(), 'to' => $now->copy()->endOfQuarter()],
            DateRangePreset::LastQuarter => ['from' => $now->copy()->subQuarter()->startOfQuarter(), 'to' => $now->copy()->subQuarter()->endOfQuarter()],
            DateRangePreset::ThisYear => ['from' => $now->copy()->startOfYear(), 'to' => $now->copy()->endOfYear()],
            DateRangePreset::LastYear => ['from' => $now->copy()->subYear()->startOfYear(), 'to' => $now->copy()->subYear()->endOfYear()],
            DateRangePreset::Custom => ['from' => null, 'to' => null],
        };
    }

    public function allPresetsWithRanges(?string $timezone = null): array
    {
        return collect(DateRangePreset::cases())
            ->reject(fn ($p) => $p === DateRangePreset::Custom)
            ->map(function ($preset) use ($timezone) {
                $range = $this->resolve($preset, $timezone);

                return [
                    'value' => $preset->value,
                    'label' => $preset->label(),
                    'from' => $range['from']->toDateString(),
                    'to' => $range['to']->toDateString(),
                ];
            })
            ->values()
            ->all();
    }
}
