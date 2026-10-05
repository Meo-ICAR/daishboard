<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Support\CompanyScope;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => CompanyScope::byDatabase($query))
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('filament/admin/project_resource.user.name'))
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('filament/admin/project_resource.name'))
                    ->searchable(),
                TextColumn::make('database')
                    ->label(__('filament/admin/project_resource.database'))
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('is_current')
                    ->label(__('filament/admin/project_resource.is_current'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('filament/admin/project_resource.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('filament/admin/project_resource.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->label(__('filament/admin/project_resource.edit')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('filament/admin/project_resource.delete_bulk')),
                ]),
            ]);
    }
}
