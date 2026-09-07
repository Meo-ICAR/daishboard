<?php

namespace App\Filament\Resources\DashboardWidgets\Schemas;

use App\Support\ChartType;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Project;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

class DashboardWidgetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('dashboard_id')
                    ->label('Dashboard')
                    ->relationship('dashboard', 'title')
                    ->live()
                    ->required(),
                Select::make('project_id')
                    ->label('Studio (filtri di coorte)')
                    ->helperText('Applica i filtri di coorte dello studio (date, flag, lookup) alla query del widget. Sono elencati solo gli studi dello stesso database della dashboard.')
                    ->relationship(
                        name: 'project',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query, Get $get): Builder {
                            $database = Dashboard::find($get('dashboard_id'))?->database;

                            return $query
                                ->when(
                                    filled($database),
                                    fn (Builder $inner): Builder => $inner->where(
                                        fn (Builder $scoped) => $scoped
                                            ->whereNull('database')
                                            ->orWhere('database', $database),
                                    ),
                                )
                                ->orderByDesc('is_current')
                                ->orderBy('name');
                        },
                    )
                    ->getOptionLabelFromRecordUsing(fn (Project $record): string => trim(
                        $record->name.($record->user?->name ? ' — '.$record->user->name : ''),
                    ))
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Select::make('chat_history_id')
                    ->label('Cronologia chat')
                    ->relationship('chatHistory', 'id'),
                Select::make('master_widget_id')
                    ->label('Widget master')
                    ->helperText('Widget di cui questo è un dettaglio/drill-down.')
                    ->relationship(
                        name: 'masterWidget',
                        titleAttribute: 'title',
                        modifyQueryUsing: fn (Builder $query, ?Model $record): Builder => $record
                            ? $query->whereKeyNot($record->getKey())
                            : $query,
                    )
                    ->searchable()
                    ->preload()
                    ->live()
                    ->nullable(),
                TextInput::make('master_filter_column')
                    ->label('Colonna di filtro dal master')
                    ->helperText('Colonna della query del widget master su cui filtrare (es. n_pazienti, totale_centri).')
                    ->placeholder('n_pazienti')
                    ->datalist(fn (Get $get): array => static::masterQueryColumns($get('master_widget_id')))
                    ->maxLength(255)
                    ->nullable(),
                TextInput::make('title')
                    ->label('Titolo'),
                Select::make('type')
                    ->label('Tipo grafico')
                    ->options(array_map(
                        fn (array $meta): string => $meta['label'].' — '.$meta['description'],
                        ChartType::all(),
                    ))
                    ->required(),
                Textarea::make('query')
                    ->label('Query SQL')
                    ->columnSpanFull(),
                TextInput::make('settings')
                    ->label('Impostazioni'),
                TextInput::make('grid_position')
                    ->label('Posizione griglia'),
                TextInput::make('order')
                    ->label('Ordine')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Attivo')
                    ->required(),
            ]);
    }

    /**
     * Nomi delle colonne restituite dalla query del widget master indicato,
     * usate come suggerimenti per il campo di filtro. Silenziosamente vuoto
     * se il widget non esiste o la query non è eseguibile.
     *
     * @return list<string>
     */
    protected static function masterQueryColumns(mixed $masterWidgetId): array
    {
        if (blank($masterWidgetId)) {
            return [];
        }

        $master = DashboardWidget::find($masterWidgetId);
        $query = rtrim(trim((string) $master?->query), ';');

        if ($query === '' || ! preg_match('/^\s*SELECT\b/i', $query)) {
            return [];
        }

        try {
            $row = DB::connection('dbai')->selectOne("SELECT * FROM ({$query}) AS master_subquery LIMIT 1");
        } catch (Throwable) {
            return [];
        }

        return $row === null ? [] : array_keys((array) $row);
    }
}
