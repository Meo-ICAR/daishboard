<x-filament-panels::page>

    @if ($errorMessage)
        <x-filament::section>
            <div class="flex items-start gap-3 rounded-lg bg-danger-50 dark:bg-danger-950 border border-danger-200 dark:border-danger-800 p-4">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 text-danger-600 dark:text-danger-400 mt-0.5 shrink-0" />
                <p class="text-sm text-danger-700 dark:text-danger-300">{{ $errorMessage }}</p>
            </div>
        </x-filament::section>
    @elseif (empty($queryRows))
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400 italic">La query non ha restituito risultati.</p>
        </x-filament::section>
    @else
        @livewire(
            \App\Filament\Widgets\DashboardWidgetChart::class,
            [
                'rows' => $queryRows,
                'labelColumn' => $labelColumn,
                'valueColumns' => $valueColumns,
                'chartType' => $chartType,
                'chartHeading' => $widgetTitle,
            ],
            key('widget-chart-'.$recordId.'-'.$chartType.'-'.$labelColumn.'-'.implode(',', $valueColumns).'-'.md5(json_encode($dateFilters)))
        )
    @endif

</x-filament-panels::page>
