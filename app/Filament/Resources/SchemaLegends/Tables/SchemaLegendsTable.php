<?php

namespace App\Filament\Resources\SchemaLegends\Tables;

use App\Models\SchemaLegend;
use App\Support\CompanyScope;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SchemaLegendsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('order')
            ->modifyQueryUsing(fn ($query) => CompanyScope::byDatabase($query))
            ->columns([
                TextColumn::make('table_name')
                    ->label(__('filament/admin/schema_legend_resource.table_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('database')
                    ->label(__('filament/admin/schema_legend_resource.database'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('label')
                    ->label(__('filament/admin/schema_legend_resource.label'))
                    ->searchable(),
                TextColumn::make('description')
                    ->label(__('filament/admin/schema_legend_resource.description'))
                    ->limit(90)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->wrap(),
                TextColumn::make('columns_count')
                    ->label(__('filament/admin/schema_legend_resource.columns_count'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('date_columns')
                    ->label(__('filament/admin/schema_legend_resource.date_columns'))
                    ->badge()
                    ->color('info')
                    ->state(fn (SchemaLegend $record): int => $record->columns()->dateFields()->count()),
                TextColumn::make('lookup_columns')
                    ->label(__('filament/admin/schema_legend_resource.lookup_columns'))
                    ->badge()
                    ->color('warning')
                    ->state(fn (SchemaLegend $record): int => $record->columns()->whereNotNull('lookup_table')->count()),
                TextColumn::make('synced_at')
                    ->label(__('filament/admin/schema_legend_resource.synced_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label(__('filament/admin/schema_legend_resource.view')),
                EditAction::make()
                    ->label(__('filament/admin/schema_legend_resource.edit'))
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
            ]);
    }
}
