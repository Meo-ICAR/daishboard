<x-filament-panels::page>

    <style>
        .dw-charts-grid {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: minmax(0, 1fr);
        }
        @media (min-width: 768px) {
            .dw-charts-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (min-width: 1280px) {
            .dw-charts-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        .dw-chart-card { min-width: 0; }
        .dw-chart-card .fi-wi-chart-frame { max-height: 16rem; }
    </style>

    @if (! empty($charts))
        <div class="dw-charts-grid">
            @foreach ($charts as $chart)
                <div @class([
                    'dw-chart-card rounded-xl',
                    'ring-2 ring-primary-500 ring-offset-2 ring-offset-white dark:ring-offset-gray-900' => $chart['isMaster'],
                ])>
                    <div class="flex items-center justify-between gap-2 pb-1">
                        <div class="flex items-center gap-1.5 min-w-0">
                            @if ($chart['isMaster'])
                                <a href="{{ $chart['viewUrl'] }}"
                                   class="truncate text-sm font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400 hover:underline"
                                   title="{{ $chart['title'] }}">
                                    {{ $chart['title'] }}
                                </a>
                                <x-filament::badge color="primary" size="sm">master</x-filament::badge>
                            @else
                                <a href="{{ $chart['drillUrl'] }}"
                                   class="truncate text-sm font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400 hover:underline"
                                   title="{{ $chart['title'] }}">
                                    {{ $chart['title'] }}
                                </a>
                                @if ($chart['hasChildren'])
                                    <x-filament::badge color="gray" size="sm">figli</x-filament::badge>
                                @endif
                            @endif
                        </div>
                    </div>

                    @if ($chart['error'])
                        <x-filament::section compact>
                            <p class="text-sm text-danger-600 dark:text-danger-400">{{ $chart['error'] }}</p>
                        </x-filament::section>
                    @elseif (empty($chart['rows']))
                        <x-filament::section compact>
                            <p class="text-sm text-gray-500 dark:text-gray-400 italic">Nessun risultato.</p>
                        </x-filament::section>
                    @elseif (empty($chart['valueColumns']))
                        <x-filament::section compact>
                            <p class="text-sm text-gray-500 dark:text-gray-400 italic">Nessuna colonna numerica da rappresentare.</p>
                        </x-filament::section>
                    @else
                        @livewire(
                            \App\Filament\Widgets\DashboardWidgetChart::class,
                            [
                                'rows' => $chart['rows'],
                                'labelColumn' => $chart['labelColumn'],
                                'valueColumns' => $chart['valueColumns'],
                                'chartType' => $chart['type'],
                                'chartHeading' => null,
                                'maxHeight' => '16rem',
                            ],
                            key('ov-chart-'.$chart['id'].'-'.$chart['type'].'-'.implode(',', $chart['valueColumns']).'-'.md5(json_encode($projectFilters)))
                        )
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400 italic">Nessun grafico da mostrare.</p>
        </x-filament::section>
    @endif

</x-filament-panels::page>
