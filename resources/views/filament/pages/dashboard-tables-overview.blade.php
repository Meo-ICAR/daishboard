<x-filament-panels::page>

    {{-- Percorso di navigazione --}}
    @if (count($trail) > 1)
        <nav class="mb-1 flex flex-wrap items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
            @foreach ($trail as $i => $crumb)
                @if ($i > 0)
                    <span class="text-gray-300 dark:text-gray-600">/</span>
                @endif
                @if ($crumb['url'])
                    <a href="{{ $crumb['url'] }}" wire:navigate
                       class="hover:text-primary-600 dark:hover:text-primary-400 hover:underline">{{ $crumb['label'] }}</a>
                @else
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $crumb['label'] }}</span>
                @endif
            @endforeach
        </nav>
    @endif

    <x-filament::section :heading="$heading">
        @if (empty($items))
            <p class="text-sm italic text-gray-500 dark:text-gray-400">Nessun elemento a questo livello.</p>
        @else
            <ul role="list" class="divide-y divide-gray-100 dark:divide-white/10 -my-2">
                @foreach ($items as $item)
                    <li class="flex items-center gap-3 py-2.5">
                        @if ($item['type'] === 'drill')
                            <x-filament::icon :icon="$item['icon']" class="h-5 w-5 shrink-0 text-primary-500" />
                            <a href="{{ $item['url'] }}" wire:navigate class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-gray-800 hover:text-primary-600 hover:underline dark:text-gray-100 dark:hover:text-primary-400">
                                    {{ $item['title'] }}
                                </span>
                                @if (! empty($item['subtitle']))
                                    <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $item['subtitle'] }}</span>
                                @endif
                            </a>
                            <span class="shrink-0 text-xs text-gray-400">{{ $item['meta'] }}</span>
                            <x-filament::icon icon="heroicon-o-chevron-right" class="h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600" />
                        @else
                            <x-filament::icon icon="heroicon-o-table-cells" class="h-5 w-5 shrink-0 text-gray-400" />
                            <a href="{{ $item['url'] }}" class="min-w-0 flex-1 truncate text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                               title="Apri le righe della tabella">
                                {{ $item['title'] }}
                            </a>
                            @if ($item['isCurrentMaster'])
                                <x-filament::badge color="primary" size="sm">master</x-filament::badge>
                            @endif
                            @if ($item['drillUrl'])
                                <a href="{{ $item['drillUrl'] }}" wire:navigate
                                   class="shrink-0 text-xs text-gray-500 hover:text-primary-600 hover:underline dark:text-gray-400">
                                    {{ $item['childCount'] }} figli &rarr;
                                </a>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>

</x-filament-panels::page>
