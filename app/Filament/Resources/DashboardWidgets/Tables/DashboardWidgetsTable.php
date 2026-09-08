<?php

namespace App\Filament\Resources\DashboardWidgets\Tables;

use App\Filament\Pages\DashboardChartsOverview;
use App\Filament\Resources\DashboardWidgets\DashboardWidgetResource;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Support\ChartType;
use App\Support\CompanyScope;
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

                // Tipo → icona + label; cliccabile → tabella se type = 'table', altrimenti grafico
                TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => ChartType::label((string) $state))
                    ->icon(fn (?string $state): Heroicon => ChartType::icon((string) $state))
                    ->url(fn (DashboardWidget $record): string => DashboardWidgetResource::getUrl(
                        strtolower((string) $record->type) === 'table' ? 'view' : 'chart',
                        ['record' => $record],
                    )),

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

                SelectFilter::make('is_table')
                    ->label('Tipo tabella')
                    ->placeholder('Tutti')
                    ->options([
                        'table' => "type = 'table'",
                        'not_table' => "type != 'table'",
                    ])
                    ->query(fn ($query, array $data) => match ($data['value'] ?? null) {
                        'table' => $query->whereRaw("COALESCE(LOWER(type), '') = 'table'"),
                        'not_table' => $query->whereRaw("COALESCE(LOWER(type), '') <> 'table'"),
                        default => $query,
                    }),
            ])
            ->recordActions([

            ])
            ->toolbarActions([

            ]);
    }
}
