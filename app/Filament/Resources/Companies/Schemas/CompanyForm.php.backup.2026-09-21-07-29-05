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
                    ->label('Nome')
                    ->required(),
                TextInput::make('urlogo')
                    ->label('URL logo'),
                TextInput::make('url_attivazione')
                    ->label('URL attivazione'),
                TextInput::make('email_admin')
                    ->label('Email amministratore')
                    ->email(),
                TextInput::make('db_secrete')
                    ->label('Chiave segreta DB'),
                TextInput::make('db_connection')
                    ->label('Connessione DB')
                    ->required()
                    ->default('mysql'),
                TextInput::make('db_host')
                    ->label('Host DB'),
                TextInput::make('db_port')
                    ->label('Porta DB')
                    ->required()
                    ->default('3306'),
                TextInput::make('db_database')
                    ->label('Nome database'),
                TextInput::make('db_username')
                    ->label('Utente DB'),
                TextInput::make('db_password')
                    ->label('Password DB')
                    ->password(),
                Textarea::make('aibackground')
                    ->label('Contesto AI')
                    ->columnSpanFull(),
            ]);
    }
}
