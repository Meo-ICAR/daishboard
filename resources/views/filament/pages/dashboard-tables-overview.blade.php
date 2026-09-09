<x-filament-panels::page>

    {{-- Le classi di griglia responsive arbitrarie non sono nella CSS precompilata di Filament. --}}
    <style>
        .dt-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; }
        @media (min-width: 640px)  { .dt-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 1280px) { .dt-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        .dt-clamp { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .dt-card { min-width: 0; }
    </style>

    @if (empty($items))
        <div class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-gray-300 bg-gray-50/60 p-12 text-center dark:border-white/10 dark:bg-white/5">
            <x-filament::icon icon="heroicon-o-table-cells" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
            <p class="text-sm text-gray-500 dark:text-gray-400">Nessuna tabella a questo livello.</p>
        </div>
    @else
        <div class="dt-grid">
            @foreach ($items as $item)
                @php
                    $isDrill = $item['type'] === 'drill';
                    $isCurrentMaster = $item['isCurrentMaster'] ?? false;
                    $icon = $item['icon'] ?? 'heroicon-o-table-cells';
                @endphp

                <div @class([
                    'dt-card group relative flex flex-col gap-3 rounded-xl border bg-white p-4 transition dark:bg-gray-900',
                    'border-primary-400 dark:border-primary-500' => $isCurrentMaster,
                    'border-gray-200 hover:border-primary-400 hover:shadow-sm dark:border-white/10 dark:hover:border-primary-500' => ! $isCurrentMaster,
                ])>
                    <div class="flex items-start gap-3">
                        <span @class([
                            'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg',
                            'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' => $isDrill || $isCurrentMaster,
                            'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400' => ! $isDrill && ! $isCurrentMaster,
                        ])>
                            <x-filament::icon :icon="$icon" class="h-5 w-5" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <a href="{{ $item['url'] }}"
                               @if ($isDrill) wire:navigate @endif
                               @unless ($isDrill) title="Apri le righe della tabella" @endunless
                               class="block truncate font-semibold text-gray-950 hover:text-primary-600 dark:text-white dark:hover:text-primary-400">
                                <span class="absolute inset-0 rounded-xl" aria-hidden="true"></span>
                                {{ $item['title'] }}
                            </a>

                            @if ($isDrill && ! empty($item['subtitle']))
                                <p class="dt-clamp mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $item['subtitle'] }}</p>
                            @elseif (! $isDrill)
                                <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                                    {{ $isCurrentMaster ? 'Tabella master di questa vista' : 'Apri per vedere le righe' }}
                                </p>
                            @endif
                        </div>

                        @if ($isDrill)
                            <x-filament::icon icon="heroicon-m-arrow-right"
                                class="mt-1 h-4 w-4 shrink-0 text-gray-300 transition group-hover:text-primary-500 dark:text-gray-600" />
                        @endif
                    </div>

                    <div class="mt-auto flex flex-wrap items-center gap-2 pt-1">
                        @if ($isDrill)
                            <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-400">
                                {{ $item['meta'] }}
                            </span>
                        @else
                            @if ($isCurrentMaster)
                                <x-filament::badge color="primary" size="sm">master</x-filament::badge>
                            @endif

                            @if (! empty($item['drillUrl']))
                                <a href="{{ $item['drillUrl'] }}" wire:navigate
                                   class="relative z-10 inline-flex items-center gap-1 rounded-md border border-gray-200 bg-white px-2 py-1 text-xs font-medium text-gray-600 transition hover:border-primary-400 hover:text-primary-600 dark:border-white/10 dark:bg-gray-900 dark:text-gray-400 dark:hover:border-primary-500">
                                    <x-filament::icon icon="heroicon-m-table-cells" class="h-3.5 w-3.5" />
                                    {{ $item['childCount'] }} collegate
                                </a>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</x-filament-panels::page>
