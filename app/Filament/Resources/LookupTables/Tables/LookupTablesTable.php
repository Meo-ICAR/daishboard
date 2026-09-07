<?php

namespace App\Filament\Resources\LookupTables\Tables;

use App\Models\LookupTable;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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
                    ->label('Tabella')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('label')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->limit(80)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->wrap(),
                TextColumn::make('row_count')
                    ->label('Righe')
                    ->badge()
                    ->sortable(),
                TextColumn::make('values_count')
                    ->label('Valori')
                    ->badge()
                    ->color('info')
                    ->state(fn (LookupTable $record): string => $record->values ? (string) count($record->values) : '—'),
                TextColumn::make('columns_count')
                    ->label('Campi collegati')
                    ->badge()
                    ->color('warning')
                    ->state(fn (LookupTable $record): int => $record->columns()->count()),
                IconColumn::make('is_dictionary')
                    ->label('Enum')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('synced_at')
                    ->label('Aggiornato')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_dictionary')
                    ->label('Solo enumerazioni'),
                TernaryFilter::make('has_links')
                    ->label('Con campi collegati')
                    ->queries(
                        true: fn ($query) => $query->has('columns'),
                        false: fn ($query) => $query->doesntHave('columns'),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
