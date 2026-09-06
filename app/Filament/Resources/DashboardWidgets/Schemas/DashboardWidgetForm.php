<?php

namespace App\Filament\Resources\DashboardWidgets\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DashboardWidgetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('dashboard_id')
                    ->relationship('dashboard', 'title')
                    ->required(),
                Select::make('chat_history_id')
                    ->relationship('chatHistory', 'id'),
                TextInput::make('title'),
                TextInput::make('type'),
                Textarea::make('query')
                    ->columnSpanFull(),
                TextInput::make('settings'),
                TextInput::make('grid_position'),
                TextInput::make('order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
