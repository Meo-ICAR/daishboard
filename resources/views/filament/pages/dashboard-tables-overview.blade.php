<x-filament-panels::page>

    @if ($level === 'categories')

        <x-filament::section>
            <ul role="list" class="divide-y divide-gray-100 dark:divide-white/10 -my-2">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ \App\Filament\Pages\DashboardTablesOverview::getUrl(['category' => $category['id']]) }}"
                           wire:navigate
                           class="flex items-center gap-3 py-3 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                            <x-filament::icon icon="heroicon-o-folder" class="h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500" />
                            <span class="flex-1 truncate">{{ $category['name'] }}</span>
                            <span class="shrink-0 text-xs font-normal text-gray-400 dark:text-gray-500">
                                {{ $category['count'] }} {{ $category['count'] === 1 ? 'tabella' : 'tabelle' }}
                            </span>
                            <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>

    @elseif (empty($sections))

        <x-filament::section>
            <p class="text-sm italic text-gray-500 dark:text-gray-400">Nessuna tabella in questa categoria.</p>
        </x-filament::section>

    @else

        <div class="space-y-4">
            @foreach ($sections as $section)
                <x-filament::section
                    :heading="$section['title']"
                    :description="count($section['tables']) . ' ' . (count($section['tables']) === 1 ? 'tabella' : 'tabelle')"
                    icon="heroicon-o-rectangle-stack"
                    collapsible
                    :collapsed="! $section['expanded']"
                    wire:key="dt-sec-{{ $section['id'] }}"
                >
                    <ul role="list" class="divide-y divide-gray-100 dark:divide-white/10 -my-2">
                        @foreach ($section['tables'] as $table)
                            <li class="py-2.5">
                                <a href="{{ $table['url'] }}"
                                   class="flex items-center gap-2.5 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                                   title="Apri le righe della tabella">
                                    <x-filament::icon icon="heroicon-o-table-cells" class="h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500" />
                                    <span class="flex-1 truncate">{{ $table['title'] }}</span>
                                </a>

                                @if (! empty($table['children']))
                                    <ul role="list" class="mt-2 ml-2.5 space-y-2 border-l border-gray-200 pl-4 dark:border-white/10">
                                        @foreach ($table['children'] as $child)
                                            <li>
                                                <a href="{{ $child['url'] }}"
                                                   class="flex items-center gap-2 text-xs font-medium text-gray-600 hover:text-primary-600 hover:underline dark:text-gray-400 dark:hover:text-primary-400"
                                                   title="Apri le righe della tabella di dettaglio">
                                                    <x-filament::icon icon="heroicon-m-table-cells" class="h-3.5 w-3.5 shrink-0" />
                                                    <span class="truncate">{{ $child['title'] }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-filament::section>
            @endforeach
        </div>

    @endif

</x-filament-panels::page>
