<?php

namespace App\Filament\Resources\LookupTables\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LookupTableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('table_name')->label('Tabella')->disabled(),
                TextInput::make('connection')->label('Connessione')->disabled(),
                TextInput::make('key_column')->label('Colonna chiave')->disabled(),
                TextInput::make('label_column')->label('Colonna etichetta')->disabled(),
                TextInput::make('label')->label('Nome leggibile')->maxLength(255),
                Textarea::make('description')->label('Descrizione')->rows(3)->columnSpanFull(),
            ]);
    }
}
