<x-filament-panels::page>

    {{-- Intestazione del widget con metadati --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <x-filament::section>
            <x-slot name="heading">Tipo</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ $record->type ?? '—' }}
            </p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Dashboard</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ $record->dashboard?->title ?? '—' }}
            </p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Stato</x-slot>
            <p class="text-sm font-medium {{ $record->is_active ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                {{ $record->is_active ? 'Attivo' : 'Non attivo' }}
            </p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Righe restituite</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                @if ($errorMessage)
                    —
                @else
                    {{ count($rows) }}
                @endif
            </p>
        </x-filament::section>
    </div>

    {{-- Query sorgente --}}
    <x-filament::section class="mb-6">
        <x-slot name="heading">Query SQL</x-slot>

        @if ($record->query)
            <pre class="text-xs bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-200 rounded-lg p-4 overflow-x-auto whitespace-pre-wrap break-words">{{ $record->query }}</pre>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400 italic">Nessuna query definita.</p>
        @endif
    </x-filament::section>

    {{-- Risultati della query --}}
    <x-filament::section>
        <x-slot name="heading">Risultati</x-slot>

        @if ($errorMessage)
            {{-- Messaggio di errore --}}
            <div class="flex items-start gap-3 rounded-lg bg-danger-50 dark:bg-danger-950 border border-danger-200 dark:border-danger-800 p-4">
                <x-filament::icon
                    icon="heroicon-o-exclamation-triangle"
                    class="h-5 w-5 text-danger-600 dark:text-danger-400 mt-0.5 shrink-0"
                />
                <p class="text-sm text-danger-700 dark:text-danger-300">{{ $errorMessage }}</p>
            </div>
        @elseif (empty($rows))
            <p class="text-sm text-gray-500 dark:text-gray-400 italic">Nessun risultato.</p>
        @else
            {{-- Tabella dinamica --}}
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            @foreach ($columns as $column)
                                <th scope="col"
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ $column }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                        @foreach ($rows as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-100">
                                @foreach ($columns as $column)
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap max-w-xs truncate"
                                        title="{{ is_null($row[$column]) ? 'NULL' : $row[$column] }}">
                                        @if (is_null($row[$column]))
                                            <span class="text-gray-400 dark:text-gray-600 italic">NULL</span>
                                        @else
                                            {{ $row[$column] }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Footer con conteggio --}}
            <p class="mt-3 text-xs text-gray-400 dark:text-gray-600">
                {{ count($rows) }} {{ count($rows) === 1 ? 'riga' : 'righe' }} restituit{{ count($rows) === 1 ? 'a' : 'e' }}
            </p>
        @endif
    </x-filament::section>

</x-filament-panels::page>
