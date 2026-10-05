<?php

namespace App\Filament\Resources\LookupTables\Tables;

use App\Models\LookupTable;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class LookupTablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('table_name')
            ->columns([
                TextColumn::make('table_name')
                    ->label(__('filament/admin/lookup_table_resource.table_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('label')
                    ->label(__('filament/admin/lookup_table_resource.label'))
                    ->searchable(),
                TextColumn::make('description')
                    ->label(__('filament/admin/lookup_table_resource.description'))
                    ->limit(80)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->wrap(),
                TextColumn::make('row_count')
                    ->label(__('filament/admin/lookup_table_resource.row_count'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('values_count')
                    ->label(__('filament/admin/lookup_table_resource.values_count'))
                    ->badge()
                    ->color('info')
                    ->state(fn (LookupTable $record): string => $record->values ? (string) count($record->values) : '—'),
                TextColumn::make('columns_count')
                    ->label(__('filament/admin/lookup_table_resource.columns_count'))
                    ->badge()
                    ->color('warning')
                    ->state(fn (LookupTable $record): int => $record->columns()->count()),
                IconColumn::make('is_dictionary')
                    ->label(__('filament/admin/lookup_table_resource.is_dictionary'))
                    ->boolean()
                    ->toggleable(),
                ToggleColumn::make('is_data_table')
                    ->label(__('filament/admin/lookup_table_resource.is_data_table'))
                    ->disabled(fn (): bool => ! (auth()->user()?->isSuperAdmin() ?? false)),
                TextColumn::make('synced_at')
                    ->label(__('filament/admin/lookup_table_resource.synced_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_dictionary')
                    ->label(__('filament/admin/lookup_table_resource.is_dictionary')),
                TernaryFilter::make('is_data_table')
                    ->label(__('filament/admin/lookup_table_resource.is_data_table'))
                    ->placeholder('Tutte')
                    ->trueLabel('Solo dati')
                    ->falseLabel('Solo lookup'),
                TernaryFilter::make('has_links')
                    ->label(__('filament/admin/lookup_table_resource.has_links'))
                    ->queries(
                        true: fn ($query) => $query->has('columns'),
                        false: fn ($query) => $query->doesntHave('columns'),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label(__('filament/admin/lookup_table_resource.view')),
                EditAction::make()
                    ->label(__('filament/admin/lookup_table_resource.edit'))
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
            ]);
    }
}
