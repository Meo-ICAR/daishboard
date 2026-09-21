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
                    ->label(__('filament/admin/company_resource.name'))
                    ->searchable(),
                TextColumn::make('urlogo')
                    ->label(__('filament/admin/company_resource.urlogo'))
                    ->searchable(),
                TextColumn::make('url_attivazione')
                    ->label(__('filament/admin/company_resource.url_attivazione'))
                    ->searchable(),
                TextColumn::make('email_admin')
                    ->label(__('filament/admin/company_resource.email_admin'))
                    ->searchable(),
                TextColumn::make('db_secrete')
                    ->label(__('filament/admin/company_resource.db_secrete'))
                    ->searchable(),
                TextColumn::make('db_connection')
                    ->label(__('filament/admin/company_resource.db_connection'))
                    ->searchable(),
                TextColumn::make('db_host')
                    ->label(__('filament/admin/company_resource.db_host'))
                    ->searchable(),
                TextColumn::make('db_port')
                    ->label(__('filament/admin/company_resource.db_port'))
                    ->searchable(),
                TextColumn::make('db_database')
                    ->label(__('filament/admin/company_resource.db_database'))
                    ->searchable(),
                TextColumn::make('db_username')
                    ->label(__('filament/admin/company_resource.db_username'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('filament/admin/company_resource.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('filament/admin/company_resource.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label(__('filament/admin/company_resource.deleted_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make()
                    ->label(__('filament/admin/company_resource.trashed')),
            ])
            ->recordActions([
                EditAction::make()
                    ->label(__('filament/admin/company_resource.edit')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('filament/admin/company_resource.delete_bulk')),
                    ForceDeleteBulkAction::make()
                        ->label(__('filament/admin/company_resource.force_delete_bulk')),
                    RestoreBulkAction::make()
                        ->label(__('filament/admin/company_resource.restore_bulk')),
                ]),
            ]);
    }
}
