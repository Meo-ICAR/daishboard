<x-filament-panels::page>

    <style>
        .dt-list > li:nth-child(odd) .dt-row { background-color: rgb(249 250 251); }
        .dark .dt-list > li:nth-child(odd) .dt-row { background-color: rgb(255 255 255 / 0.04); }
        .dt-row { display: flex; align-items: baseline; gap: 0.5rem; padding: 0.375rem 0.5rem; border-radius: 0.375rem; }
        .dt-bullet { flex: none; color: rgb(156 163 175); font-size: 0.75rem; line-height: 1.25rem; }
        .dt-link { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dt-children { margin-top: 0.375rem; margin-left: 0.5rem; padding-left: 0.875rem; border-left: 1px solid rgb(229 231 235); }
        .dark .dt-children { border-left-color: rgb(255 255 255 / 0.1); }
        .dt-child-row { display: flex; align-items: baseline; gap: 0.5rem; padding: 0.1875rem 0; }
        .dt-child-row .dt-bullet { font-size: 0.625rem; }
    </style>

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
                    <ul role="list" class="dt-list -my-1">
                        @foreach ($section['tables'] as $table)
                            <li>
                                <div class="dt-row">
                                    <span class="dt-bullet" aria-hidden="true">&bull;</span>
                                    <a href="{{ $table['url'] }}"
                                       class="dt-link text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                                       title="Apri le righe della tabella">{{ $table['title'] }}</a>
                                </div>

                                @if (! empty($table['children']))
                                    <ul role="list" class="dt-children">
                                        @foreach ($table['children'] as $child)
                                            <li class="dt-child-row">
                                                <span class="dt-bullet" aria-hidden="true">&ndash;</span>
                                                <a href="{{ $child['url'] }}"
                                                   class="dt-link text-xs font-medium text-gray-600 hover:text-primary-600 hover:underline dark:text-gray-400 dark:hover:text-primary-400"
                                                   title="Apri le righe della tabella di dettaglio">{{ $child['title'] }}</a>
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
