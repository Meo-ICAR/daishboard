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
                TextInput::make('table_name')->label(__('filament/admin/lookup_table_resource.table_name'))->disabled(),
                TextInput::make('connection')->label(__('filament/admin/lookup_table_resource.connection'))->disabled(),
                TextInput::make('key_column')->label(__('filament/admin/lookup_table_resource.key_column'))->disabled(),
                TextInput::make('label_column')->label(__('filament/admin/lookup_table_resource.label_column'))->disabled(),
                TextInput::make('label')->label(__('filament/admin/lookup_table_resource.label'))->maxLength(255),
                Textarea::make('description')->label(__('filament/admin/lookup_table_resource.description'))->rows(3)->columnSpanFull(),
            ]);
    }
}
