<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->label(__('filament/admin/user_resource.company_id'))
                    ->relationship('company', 'name'),
                TextInput::make('name')
                    ->label(__('filament/admin/user_resource.name'))
                    ->required(),
                TextInput::make('email')
                    ->label(__('filament/admin/user_resource.email'))
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at')
                    ->label(__('filament/admin/user_resource.email_verified_at')),
                TextInput::make('password')
                    ->label(__('filament/admin/user_resource.password'))
                    ->password()
                    ->required(),
                Toggle::make('is_admin')
                    ->label(__('filament/admin/user_resource.is_admin'))
                    ->required(),
            ]);
    }
}
