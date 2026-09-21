<?php

namespace App\Filament\Resources\Companies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('urlogo')
                    ->label('URL logo')
                    ->searchable(),
                TextColumn::make('url_attivazione')
                    ->label('URL attivazione')
                    ->searchable(),
                TextColumn::make('email_admin')
                    ->label('Email amministratore')
                    ->searchable(),
                TextColumn::make('db_secrete')
                    ->label('Chiave segreta DB')
                    ->searchable(),
                TextColumn::make('db_connection')
                    ->label('Connessione DB')
                    ->searchable(),
                TextColumn::make('db_host')
                    ->label('Host DB')
                    ->searchable(),
                TextColumn::make('db_port')
                    ->label('Porta DB')
                    ->searchable(),
                TextColumn::make('db_database')
                    ->label('Nome database')
                    ->searchable(),
                TextColumn::make('db_username')
                    ->label('Utente DB')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Creata il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Modificata il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Eliminata il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
