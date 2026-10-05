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
                    ->label('Tabella')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('database')
                    ->label('Database')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('label')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->limit(90)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->wrap(),
                TextColumn::make('columns_count')
                    ->label('Campi')
                    ->badge()
                    ->sortable(),
                TextColumn::make('date_columns')
                    ->label('Campi data')
                    ->badge()
                    ->color('info')
                    ->state(fn (SchemaLegend $record): int => $record->columns()->dateFields()->count()),
                TextColumn::make('lookup_columns')
                    ->label('Campi lookup')
                    ->badge()
                    ->color('warning')
                    ->state(fn (SchemaLegend $record): int => $record->columns()->whereNotNull('lookup_table')->count()),
                TextColumn::make('synced_at')
                    ->label('Aggiornato')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
            ]);
    }
}
