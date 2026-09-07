<?php

namespace App\Filament\Resources\SchemaLegends\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SchemaLegendInfolist
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
                        TextEntry::make('columns_count')->label('Campi')->badge(),
                        TextEntry::make('synced_at')->label('Ultima sincronizzazione')->dateTime(),
                        TextEntry::make('description')
                            ->label('Descrizione')
                            ->columnSpanFull()
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
