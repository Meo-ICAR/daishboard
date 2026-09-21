<?php

namespace App\Filament\Resources\SchemaLegends\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SchemaLegendForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('table_name')
                    ->label('Tabella')
                    ->disabled(),
                TextInput::make('connection')
                    ->label('Connessione')
                    ->disabled(),
                TextInput::make('database')
                    ->label('Database')
                    ->maxLength(255),
                TextInput::make('label')
                    ->label('Nome leggibile')
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Descrizione')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
