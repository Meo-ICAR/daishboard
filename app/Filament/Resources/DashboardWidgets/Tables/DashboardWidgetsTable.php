<?php

namespace App\Filament\Resources\DashboardWidgets\Tables;

use App\Filament\Resources\DashboardWidgets\DashboardWidgetResource;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DashboardWidgetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('dashboard.title')
                    ->searchable(),

                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('masterWidget.title')
                    ->label('Widget master')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('master_filter_column')
                    ->label('Colonna filtro master')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->boolean(),

            ])
            ->filters([
                SelectFilter::make('dashboard_id')
                    ->label('Dashboard')
                    ->relationship('dashboard', 'title')
                    ->default(Dashboard::query()->orderBy('order')->orderBy('id')->value('id'))
                    ->preload()
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('chart')
                    ->label('Grafico')
                    ->icon(Heroicon::OutlinedChartBar)
                    ->url(fn (DashboardWidget $record): string => DashboardWidgetResource::getUrl('chart', ['record' => $record])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
