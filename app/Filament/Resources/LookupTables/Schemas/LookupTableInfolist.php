<?php

namespace App\Filament\Resources\LookupTables\Schemas;

use App\Models\LookupTable;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LookupTableInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('table_name')->label('Tabella'),
                        TextEntry::make('connection')->label('Connessione')->badge(),
                        TextEntry::make('label')->label('Nome leggibile'),
                        TextEntry::make('key_column')->label('Colonna chiave'),
                        TextEntry::make('label_column')->label('Colonna etichetta')->placeholder('—'),
                        TextEntry::make('row_count')->label('Righe')->badge(),
                        TextEntry::make('is_dictionary')
                            ->label('Enumerazione')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'sì' : 'no (entità)')
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                        TextEntry::make('columns_count')
                            ->label('Campi collegati')
                            ->badge()
                            ->state(fn (LookupTable $record): int => $record->columns()->count()),
                        TextEntry::make('synced_at')->label('Aggiornato')->dateTime(),
                        TextEntry::make('description')->label('Descrizione')->columnSpanFull()->placeholder('—'),
                    ]),

                Section::make('Valori')
                    ->description(fn (LookupTable $record): string => $record->values
                        ? count($record->values).' valori'
                        : 'Non elencati (tabella grande) — usa "Mostra valori"')
                    ->collapsible()
                    ->visible(fn (LookupTable $record): bool => filled($record->values))
                    ->schema([
                        RepeatableEntry::make('values')
                            ->hiddenLabel()
                            ->columns(2)
                            ->schema([
                                TextEntry::make('value')->label('Valore')->placeholder('∅'),
                                TextEntry::make('label')->label('Etichetta')->placeholder('∅'),
                            ]),
                    ]),
            ]);
    }
}
