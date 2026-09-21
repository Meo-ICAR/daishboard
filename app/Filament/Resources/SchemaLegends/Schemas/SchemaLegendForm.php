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
                    ->label(__('filament/admin/schema_legend_resource.table_name'))
                    ->disabled(),
                TextInput::make('connection')
                    ->label(__('filament/admin/schema_legend_resource.connection'))
                    ->disabled(),
                TextInput::make('database')
                    ->label(__('filament/admin/schema_legend_resource.database'))
                    ->maxLength(255),
                TextInput::make('label')
                    ->label(__('filament/admin/schema_legend_resource.label'))
                    ->maxLength(255),
                Textarea::make('description')
                    ->label(__('filament/admin/schema_legend_resource.description'))
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
