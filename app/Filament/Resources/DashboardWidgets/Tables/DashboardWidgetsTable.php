<?php

namespace App\Filament\Resources\DashboardWidgets\Tables;

use App\Filament\Pages\DashboardChartsOverview;
use App\Filament\Resources\DashboardWidgets\DashboardWidgetResource;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\MenuCategory;
use App\Models\User;
use App\Support\ChartType;
use App\Support\CompanyScope;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DashboardWidgetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => CompanyScope::byOwner($query))
            ->columns([

                // Dashboard → apre dashboard-charts-overview?dashboardId={id}
                TextColumn::make('dashboard.name')
                    ->label(__('filament/admin/dashboard_widget_resource.dashboard.name'))
                    ->searchable()
                    ->sortable()
                    ->url(fn (DashboardWidget $record): ?string => $record->dashboard_id !== null
                        ? DashboardChartsOverview::getUrl(['dashboardId' => $record->dashboard_id])
                        : null)
                    ->openUrlInNewTab(false),

                // Titolo → apre dashboard-widgets/{id} (view)
                TextColumn::make('title')
                    ->label(__('filament/admin/dashboard_widget_resource.title'))
                    ->searchable()
                    ->sortable()
                    ->url(fn (DashboardWidget $record): string => DashboardWidgetResource::getUrl('view', ['record' => $record])),

                // Tipo → icona + label; cliccabile → tabella se type = 'table', altrimenti grafico
                TextColumn::make('type')
                    ->label(__('filament/admin/dashboard_widget_resource.type'))
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): string => ChartType::label((string) $state))
                    ->icon(fn (?string $state): Heroicon => ChartType::icon((string) $state))
                    ->url(fn (DashboardWidget $record): string => DashboardWidgetResource::getUrl(
                        strtolower((string) $record->type) === 'table' ? 'view' : 'chart',
                        ['record' => $record],
                    )),

                TextColumn::make('masterWidget.title')
                    ->label(__('filament/admin/dashboard_widget_resource.master_widget.title'))
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('master_filter_column')
                    ->label(__('filament/admin/dashboard_widget_resource.master_filter_column'))
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('dashboard_id')
                    ->label(__('filament/admin/dashboard_widget_resource.dashboard_id'))
                    ->relationship('dashboard', 'name')
                    ->default(auth()->user()?->dashboard_id
                        ?? Dashboard::query()->orderBy('order')->orderBy('id')->value('id'))
                    ->preload()
                    ->searchable(),

                SelectFilter::make('menu_category')
                    ->label(__('filament/admin/dashboard_widget_resource.menu_category'))
                    ->options(fn (): array => MenuCategory::query()
                        ->orderBy('order')->orderBy('name')
                        ->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $q): Builder => $q->whereHas(
                            'dashboard',
                            fn (Builder $d) => $d->where('menu_category_id', $data['value']),
                        ),
                    )),

                SelectFilter::make('project_id')
                    ->label(__('filament/admin/dashboard_widget_resource.project_id'))
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('is_table')
                    ->label(__('filament/admin/dashboard_widget_resource.is_table'))
                    ->placeholder('Tutti')
                    ->options([
                        'table' => 'Tabelle',
                        'not_table' => 'Grafici',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'table' => $query->whereRaw("COALESCE(LOWER(type), '') = 'table'"),
                        'not_table' => $query->whereRaw("COALESCE(LOWER(type), '') <> 'table'"),
                        default => $query,
                    }),
                TernaryFilter::make('master_widget')
                    ->label(__('filament/admin/dashboard_widget_resource.master_widget'))
                    ->placeholder('Tutti')
                    ->trueLabel('Con dettagli')
                    ->falseLabel('Senza dettagli')
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'true' => $query->whereNotNull('master_widget'),
                        'false' => $query->whereNull('master_widget'),
                        default => $query,
                    }),

                TernaryFilter::make('is_active')
                    ->label(__('filament/admin/dashboard_widget_resource.is_active'))
                    ->queries(
                        true: fn ($query) => $query->where('is_active', true),
                        false: fn ($query) => $query->where('is_active', false),
                    )
                    ->placeholder('Tutti')
                    ->trueLabel('Solo Attivi')
                    ->falseLabel('Solo Dimessi')
                    ->default(true),

            ])
            ->recordActions([
                EditAction::make()
                    ->label(__('filament/admin/dashboard_widget_resource.edit')),

                Action::make('duplicate')
                    ->label(__('filament/admin/dashboard_widget_resource.duplicate'))
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->modalHeading('Duplica estrazione dati')
                    ->modalSubmitActionLabel('Duplica')
                    // Il modale con la scelta dell'utente compare solo se il widget
                    // ha un proprietario; altrimenti la duplica parte subito.
                    ->schema(fn (DashboardWidget $record): array => $record->user_id === null ? [] : [
                        Select::make('user_id')
                            ->label('Assegna all\'utente')
                            ->helperText('Predefinito: lo stesso utente. Lascia vuoto sesenza proprietario.')
                            ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->default($record->user_id)
                            ->searchable()
                            ->nullable(),
                    ])
                    ->action(function (DashboardWidget $record, array $data): void {
                        $copy = $record->replicate();
                        $copy->title = trim(($record->title ?? 'Widget').' (copia)');

                        if (array_key_exists('user_id', $data)) {
                            $copy->user_id = $data['user_id'];
                        }

                        $copy->saveQuietly();

                        Notification::make()
                            ->title('Widget duplicato')
                            ->body($copy->title)
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([

            ]);
    }
}
