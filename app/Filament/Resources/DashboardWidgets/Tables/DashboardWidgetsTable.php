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
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                TextColumn::make('dashboard.title')
                    ->label('Dashboard')
                    ->searchable()
                    ->sortable()
                    ->url(fn (DashboardWidget $record): ?string => $record->dashboard_id !== null
                        ? DashboardChartsOverview::getUrl(['dashboardId' => $record->dashboard_id])
                        : null)
                    ->openUrlInNewTab(false),

                // Titolo → apre dashboard-widgets/{id} (view)
                TextColumn::make('title')
                    ->label('Titolo')
                    ->searchable()
                    ->sortable()
                    ->url(fn (DashboardWidget $record): string => DashboardWidgetResource::getUrl('view', ['record' => $record])),

                // Tipo → icona + label; cliccabile → tabella se type = 'table', altrimenti grafico
                TextColumn::make('type')
                    ->label('Tipo')
                    ->sortable()
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
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('master_filter_column')
                    ->label('Colonna filtro master')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('dashboard_id')
                    ->label('Dashboard')
                    ->relationship('dashboard', 'title')
                    ->default(auth()->user()?->dashboard_id
                        ?? Dashboard::query()->orderBy('order')->orderBy('id')->value('id'))
                    ->preload()
                    ->searchable(),

                SelectFilter::make('menu_category')
                    ->label('Categoria di menu')
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
                    ->label('Restrizione')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('is_table')
                    ->label('Tipo tabella')
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
            ])
            ->recordActions([
                Action::make('duplicate')
                    ->label('Duplica')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->modalHeading('Duplica widget')
                    ->modalSubmitActionLabel('Duplica')
                    // Il modale con la scelta dell'utente compare solo se il widget
                    // ha un proprietario; altrimenti la duplica parte subito.
                    ->schema(fn (DashboardWidget $record): array => $record->user_id === null ? [] : [
                        Select::make('user_id')
                            ->label('Assegna all\'utente')
                            ->helperText('Predefinito: lo stesso utente. Lascia vuoto per un widget senza proprietario.')
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
