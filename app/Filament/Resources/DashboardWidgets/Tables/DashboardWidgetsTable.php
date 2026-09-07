<?php

namespace App\Filament\Resources\DashboardWidgets\Tables;

use App\Filament\Pages\DashboardChartsOverview;
use App\Filament\Resources\DashboardWidgets\DashboardWidgetResource;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Support\ChartType;
use App\Support\CompanyScope;
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
            ->modifyQueryUsing(fn ($query) => CompanyScope::byOwner($query))
            ->columns([

                // Dashboard → apre dashboard-charts-overview?dashboardId={id}
                TextColumn::make('dashboard.title')
                    ->label('Dashboard')
                    ->searchable()
                    ->url(fn (DashboardWidget $record): ?string => $record->dashboard_id !== null
                        ? DashboardChartsOverview::getUrl(['dashboardId' => $record->dashboard_id])
                        : null)
                    ->openUrlInNewTab(false),

                // Titolo → apre dashboard-widgets/{id} (view)
                TextColumn::make('title')
                    ->label('Titolo')
                    ->searchable()
                    ->url(fn (DashboardWidget $record): string => DashboardWidgetResource::getUrl('view', ['record' => $record])),

                // Tipo → icona + label; cliccabile → chart
                TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => ChartType::label((string) $state))
                    ->icon(fn (?string $state): Heroicon => ChartType::icon((string) $state))
                    ->url(fn (DashboardWidget $record): string => DashboardWidgetResource::getUrl('chart', ['record' => $record])),

                // Ordine → cliccabile → edit
                TextColumn::make('order')
                    ->label('Ordine')
                    ->numeric()
                    ->sortable()
                    ->url(fn (DashboardWidget $record): string => DashboardWidgetResource::getUrl('edit', ['record' => $record])),

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
                    ->label('Attivo')
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
