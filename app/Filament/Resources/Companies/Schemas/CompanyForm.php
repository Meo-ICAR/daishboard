<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('filament/admin/company_resource.name'))
                    ->required(),
                TextInput::make('urlogo')
                    ->label(__('filament/admin/company_resource.urlogo')),
                TextInput::make('url_attivazione')
                    ->label(__('filament/admin/company_resource.url_attivazione')),
                TextInput::make('email_admin')
                    ->label(__('filament/admin/company_resource.email_admin'))
                    ->email(),
                TextInput::make('db_secrete')
                    ->label(__('filament/admin/company_resource.db_secrete')),
                TextInput::make('db_connection')
                    ->label(__('filament/admin/company_resource.db_connection'))
                    ->required()
                    ->default('mysql'),
                TextInput::make('db_host')
                    ->label(__('filament/admin/company_resource.db_host')),
                TextInput::make('db_port')
                    ->label(__('filament/admin/company_resource.db_port'))
                    ->required()
                    ->default('3306'),
                TextInput::make('db_database')
                    ->label(__('filament/admin/company_resource.db_database')),
                TextInput::make('db_username')
                    ->label(__('filament/admin/company_resource.db_username')),
                TextInput::make('db_password')
                    ->label(__('filament/admin/company_resource.db_password'))
                    ->password(),
                Textarea::make('aibackground')
                    ->label(__('filament/admin/company_resource.aibackground'))
                    ->columnSpanFull(),
            ]);
    }
}
