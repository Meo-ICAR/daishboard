<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('urlogo'),
                TextInput::make('url_attivazione'),
                TextInput::make('email_admin')
                    ->email(),
                TextInput::make('db_secrete'),
                TextInput::make('db_connection')
                    ->required()
                    ->default('mysql'),
                TextInput::make('db_host'),
                TextInput::make('db_port')
                    ->required()
                    ->default('3306'),
                TextInput::make('db_database'),
                TextInput::make('db_username'),
                TextInput::make('db_password')
                    ->password(),
                Textarea::make('aibackground')
                    ->columnSpanFull(),
            ]);
    }
}
