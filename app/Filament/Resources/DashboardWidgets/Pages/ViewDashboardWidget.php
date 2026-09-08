<?php

namespace App\Filament\Resources\DashboardWidgets\Pages;

use App\Exports\WidgetDatasetExport;
use App\Filament\Resources\DashboardWidgets\Concerns\InteractsWithWidgetDataset;
use App\Filament\Resources\DashboardWidgets\DashboardWidgetResource;
use App\Models\DashboardWidget;
use App\Models\DashboardWidgetShare;
use App\Support\DataNavigatorProfile;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Maatwebsite\Excel\Facades\Excel;

class ViewDashboardWidget extends Page implements HasTable
{
    use InteractsWithTable;
    use InteractsWithWidgetDataset;

    protected static string $resource = DashboardWidgetResource::class;

    protected string $view = 'filament.resources.dashboard-widgets.pages.view-dashboard-widget';

    /**
     * Valore della prima colonna del master relativo alla riga da cui si è
     * arrivati (query string `MasterFilterField`). Mostrato come sottotitolo.
     */
    #[Url(as: 'MasterFilterField', keep: false)]
    public ?string $masterFilterField = null;

    /**
     * Drill-down generato: mappa base64url(JSON) `espressione SQL => valore`
     * delle dimensioni della riga aggregata cliccata. Quando valorizzato, la
     * pagina esegue l'SQL di dettaglio invece della query aggregata del widget.
     */
    #[Url(as: 'drill', keep: false)]
    public ?string $drill = null;

    /**
     * Filtri di drill-down decodificati.
     *
     * @var list<array{label: string, expr: string, value: mixed}>
     */
    #[Locked]
    public array $drillFilters = [];

    /** @var array<string, string>|null Cache della mappa alias => espressione. */
    private ?array $dimensionExpressionCache = null;

    /**
     * Widget figli che puntano a questo widget come master.
     *
     * @var array<int, array{id: int, title: ?string, column: ?string}>
     */
    #[Locked]
    public array $drilldowns = [];

    /** URL dell'ultimo link di condivisione creato, mostrato in un banner. */
    #[Locked]
    public ?string $lastShareUrl = null;

    public function mount(int|string $record): void
    {
        $widget = $this->bootWidgetDataset($record);

        if ($this->masterFilterField === '') {
            $this->masterFilterField = null;
        }

        $this->drillFilters = $this->decodeDrill($this->drill);

        $this->drilldowns = DashboardWidget::query()
            ->where('master_widget_id', $widget->getKey())
            ->orderBy('order')
            ->orderBy('id')
            ->get(['id', 'title', 'master_filter_column'])
            ->map(fn (DashboardWidget $child): array => [
                'id' => $child->getKey(),
                'title' => $child->title,
                'column' => $child->master_filter_column,
            ])
            ->all();

        $this->runQuery();
    }

    protected function afterQueryRefreshed(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (?string $sortColumn, ?string $sortDirection, int $page, int $recordsPerPage, ?string $search): LengthAwarePaginator {
                // Le colonne sono esposte come `col_<indice>`: risale al nome reale del campo.
                $sortField = filled($sortColumn)
                    ? ($this->queryColumns[(int) substr($sortColumn, 4)] ?? null)
                    : null;

                $rows = collect($this->queryRows)
                    ->when(
                        filled($search),
                        fn (Collection $data): Collection => $data->filter(
                            fn (array $row): bool => $this->rowMatchesSearch($row, (string) $search),
                        ),
                    )
                    ->when(
                        filled($sortField),
                        fn (Collection $data): Collection => $data->sortBy(
                            $sortField,
                            SORT_NATURAL | SORT_FLAG_CASE,
                            $sortDirection === 'desc',
                        ),
                    )
                    ->values();

                return new LengthAwarePaginator(
                    $rows->forPage($page, $recordsPerPage)->all(),
                    total: $rows->count(),
                    perPage: $recordsPerPage,
                    currentPage: $page,
                );
            })
            // Il titolo è già nell'header di pagina e nel breadcrumb: non lo si
            // ripete nell'intestazione della tabella.
            ->description($this->activeDateFilterDescription())
            ->headerActions($this->tableHeaderActions())
            ->columns($this->buildColumns())
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(25)
            ->emptyStateIcon(Heroicon::OutlinedTableCells)
            ->emptyStateHeading($this->errorMessage ?? 'La query non ha restituito risultati.');
    }

    /**
     * Costruisce una colonna di testo Filament per ogni campo restituito dalla query.
     * Le colonne numeriche diventano link di drill-down quando esiste almeno un
     * widget figlio con `master_filter_column` nullo o uguale al nome della colonna.
     *
     * @return array<int, TextColumn>
     */
    protected function buildColumns(): array
    {
        $columns = [];

        foreach ($this->queryColumns as $index => $name) {
            $column = TextColumn::make('col_'.$index)
                ->label($name)
                ->state(fn (array $record): mixed => $record[$name] ?? null)
                ->placeholder('NULL')
                ->sortable()
                ->wrap()
                ->toggleable();

            // Ogni campo non numerico è ricercabile (ricerca gestita in records()).
            if (! in_array($name, $this->numericColumns, true)) {
                $column->searchable();
            }

            $drilldown = in_array($name, $this->numericColumns, true)
                ? $this->drilldownFor($name)
                : null;

            if ($drilldown !== null) {
                $column
                    ->color('primary')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->iconPosition(IconPosition::After)
                    ->url(function (array $record) use ($name, $drilldown): ?string {
                        if (! array_key_exists($name, $record) || $record[$name] === null) {
                            return null;
                        }

                        $firstColumn = $this->queryColumns[0] ?? null;

                        return DashboardWidgetResource::getUrl('view', ['record' => $drilldown['id']])
                            .'?'.http_build_query([
                                'masterFilterColumn' => $name,
                                'masterFilterValue' => $record[$name],
                                'MasterFilterField' => $firstColumn !== null ? ($record[$firstColumn] ?? null) : null,
                            ]);
                    });
            } elseif ($this->drillFilters === []
                && in_array($name, $this->numericColumns, true)
                && $this->dimensionExpressions() !== []) {
                // Nessun widget figlio: drill-down generato sull'SQL del widget,
                // che apre in questa stessa tabella i record di dettaglio della riga.
                $column
                    ->color('primary')
                    ->icon(Heroicon::OutlinedMagnifyingGlassPlus)
                    ->iconPosition(IconPosition::After)
                    ->url(function (array $record) use ($name): ?string {
                        if (! array_key_exists($name, $record) || $record[$name] === null) {
                            return null;
                        }

                        $filters = $this->drillFiltersForRow($record);

                        if ($filters === []) {
                            return null;
                        }

                        return DashboardWidgetResource::getUrl('view', ['record' => $this->recordId])
                            .'?'.http_build_query(['drill' => $this->encodeDrill($filters)]);
                    });
            }

            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * Vero se una riga contiene la stringa cercata in almeno una colonna non
     * numerica (confronto case-insensitive su sottostringa).
     *
     * @param  array<string, mixed>  $row
     */
    protected function rowMatchesSearch(array $row, string $search): bool
    {
        $needle = Str::lower(trim($search));

        if ($needle === '') {
            return true;
        }

        foreach ($this->queryColumns as $name) {
            if (in_array($name, $this->numericColumns, true)) {
                continue;
            }

            if (str_contains(Str::lower((string) ($row[$name] ?? '')), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Primo widget figlio applicabile alla colonna indicata: `master_filter_column`
     * nullo (vale per qualsiasi colonna) oppure esattamente uguale alla colonna.
     *
     * @return array{id: int, title: ?string, column: ?string}|null
     */
    protected function drilldownFor(string $column): ?array
    {
        foreach ($this->drilldowns as $drilldown) {
            if ($drilldown['column'] === null || $drilldown['column'] === $column) {
                return $drilldown;
            }
        }

        return null;
    }

    /**
     * Query eseguita: quella del widget, oppure — in modalità drill-down — la
     * query di dettaglio derivata rimuovendo il raggruppamento e filtrando i
     * record di base sulle dimensioni della riga cliccata.
     */
    protected function datasetQuery(): ?string
    {
        $model = new DashboardWidget;
        $identifier = DataNavigatorProfile::identifierColumn();

        if ($this->drillFilters === []) {
            $sql = (string) $this->widgetQuery;

            return $identifier !== null ? $model->swapSelectIdentifier($sql, $identifier) : $this->widgetQuery;
        }

        $predicates = collect($this->drillFilters)
            ->mapWithKeys(fn (array $filter): array => [$filter['expr'] => $filter['value'] ?? null])
            ->all();

        $sql = $model->convertSqlStringToDrillDown((string) $this->widgetQuery, $predicates);

        if ($identifier !== null) {
            $sql = $model->swapSelectIdentifier($sql, $identifier);
        }

        if (preg_match('/\bLIMIT\s+\d/i', $sql) !== 1) {
            $sql .= ' LIMIT 2000';
        }

        return $sql;
    }

    /**
     * Mappa `alias => espressione SQL` delle dimensioni del GROUP BY della query
     * del widget (memoizzata per richiesta).
     *
     * @return array<string, string>
     */
    protected function dimensionExpressions(): array
    {
        return $this->dimensionExpressionCache ??= (new DashboardWidget)
            ->selectDimensionExpressions((string) $this->widgetQuery);
    }

    /**
     * Filtri di drill-down per una riga risultato: una voce per ogni dimensione
     * (colonna non numerica) mappabile a un'espressione SQL. Ogni voce tiene sia
     * l'etichetta leggibile (nome colonna) sia l'espressione SQL da filtrare.
     *
     * @param  array<string, mixed>  $record
     * @return list<array{label: string, expr: string, value: mixed}>
     */
    protected function drillFiltersForRow(array $record): array
    {
        $expressions = $this->dimensionExpressions();
        $filters = [];

        foreach ($record as $alias => $value) {
            if (in_array($alias, $this->numericColumns, true)) {
                continue;
            }

            $expression = $expressions[strtolower((string) $alias)] ?? null;

            if ($expression !== null) {
                $filters[] = ['label' => (string) $alias, 'expr' => $expression, 'value' => $value];
            }
        }

        return $filters;
    }

    /**
     * @param  list<array{label: string, expr: string, value: mixed}>  $filters
     */
    protected function encodeDrill(array $filters): string
    {
        return rtrim(strtr(base64_encode(
            (string) json_encode($filters, JSON_UNESCAPED_UNICODE),
        ), '+/', '-_'), '=');
    }

    /**
     * Accetta sia la forma corrente (lista di {label, expr, value}) sia quella
     * precedente (mappa espressione => valore), da vecchi link salvati.
     *
     * @return list<array{label: string, expr: string, value: mixed}>
     */
    protected function decodeDrill(?string $encoded): array
    {
        if (! is_string($encoded) || $encoded === '') {
            return [];
        }

        $json = base64_decode(strtr($encoded, '-_', '+/'), true);
        $data = $json === false ? null : json_decode($json, true);

        if (! is_array($data) || $data === []) {
            return [];
        }

        if (array_is_list($data)) {
            return array_values(array_filter(
                $data,
                fn ($filter): bool => is_array($filter) && isset($filter['expr']),
            ));
        }

        $filters = [];

        foreach ($data as $expr => $value) {
            $filters[] = ['label' => (string) $expr, 'expr' => (string) $expr, 'value' => $value];
        }

        return $filters;
    }

    /**
     * Azioni mostrate nell'intestazione della tabella (non nell'header di
     * pagina, così titolo e sottotitolo restano a piena larghezza).
     *
     * @return array<int, Action>
     */
    protected function tableHeaderActions(): array
    {
        return [
            Action::make('backToAggregate')
                ->label('Torna all\'aggregato')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->visible(fn (): bool => $this->drillFilters !== [])
                ->url(fn (): string => DashboardWidgetResource::getUrl('view', ['record' => $this->recordId])),

            ...$this->dateFilterHeaderActions(),

            Action::make('runQuery')
                ->label('Esegui di nuovo')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(fn () => $this->runQuery()),

            Action::make('exportExcel')
                ->label('Esporta Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => $this->queryRows !== [])
                ->action(function () {
                    $name = Str::slug($this->widgetTitle ?? ('widget-'.$this->recordId)) ?: 'export';

                    return Excel::download(
                        new WidgetDatasetExport(
                            $this->queryColumns,
                            $this->queryRows,
                            $this->widgetTitle ?? ('Widget '.$this->recordId),
                        ),
                        $name.'-'.now()->format('Ymd-His').'.xlsx',
                    );
                }),

            Action::make('chart')
                ->label('Grafico')
                ->icon(Heroicon::OutlinedChartBar)
                ->color('gray')
                ->url(fn (): string => $this->widgetResourceUrl('chart')),

            Action::make('share')
                ->label('Condividi')
                ->icon(Heroicon::OutlinedShare)
                ->color('gray')
                ->hidden(fn (): bool => DashboardWidgetShare::query()->where('dashboard_widget_id', $this->recordId)->exists())
                ->modalHeading('Crea un link pubblico')
                ->modalDescription('Genera un link, accessibile senza login, che mostra questa tabella con i filtri correnti memorizzati.')
                ->modalSubmitActionLabel('Crea link')
                ->fillForm(fn (): array => [
                    'title' => $this->widgetTitle,
                    'expiry' => '30',
                    'include_children' => true,
                ])
                ->schema([
                    TextInput::make('title')
                        ->label('Titolo mostrato')
                        ->maxLength(255),
                    Select::make('expiry')
                        ->label('Scadenza')
                        ->options([
                            '7' => '7 giorni',
                            '30' => '30 giorni',
                            '90' => '90 giorni',
                            '' => 'Nessuna scadenza',
                        ])
                        ->default('30')
                        ->selectablePlaceholder(false),
                    Toggle::make('include_children')
                        ->label('Consenti di aprire le tabelle figlio')
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    $share = DashboardWidgetShare::create([
                        'token' => DashboardWidgetShare::generateToken(),
                        'dashboard_widget_id' => $this->recordId,
                        'project_id' => $this->widgetProjectId,
                        'created_by' => auth()->id(),
                        'title' => filled($data['title'] ?? null) ? $data['title'] : null,
                        'parameters' => ['dateFilters' => $this->activeDateFilters()],
                        'include_children' => (bool) ($data['include_children'] ?? true),
                        'expires_at' => filled($data['expiry'] ?? null)
                            ? now()->addDays((int) $data['expiry'])
                            : null,
                    ]);

                    $this->lastShareUrl = $share->publicUrl();

                    Notification::make()
                        ->title('Link pubblico creato')
                        ->body($this->lastShareUrl)
                        ->success()
                        ->persistent()
                        ->send();
                }),

            Action::make('sharesList')
                ->label('Link condivisi')
                ->icon(Heroicon::OutlinedLink)
                ->color('gray')
                ->badge(fn (): ?string => ($n = DashboardWidgetShare::query()
                    ->where('dashboard_widget_id', $this->recordId)->count()) > 0 ? (string) $n : null)
                ->visible(fn (): bool => DashboardWidgetShare::query()->where('dashboard_widget_id', $this->recordId)->exists())
                ->modalHeading('Link pubblici di questa tabella')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Chiudi')
                ->modalContent(fn () => view('filament.resources.dashboard-widgets.partials.shares-list', [
                    'shares' => DashboardWidgetShare::query()
                        ->where('dashboard_widget_id', $this->recordId)
                        ->latest()
                        ->get(),
                ])),

            Action::make('revokeShares')
                ->label('Revoca link')
                ->visible(false)
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                // ->visible(fn (): bool => DashboardWidgetShare::query()->where('dashboard_widget_id', $this->recordId)->exists())
                ->requiresConfirmation()
                ->modalDescription('Tutti i link pubblici di questa tabella smetteranno di funzionare.')
                ->action(function (): void {
                    $deleted = DashboardWidgetShare::query()
                        ->where('dashboard_widget_id', $this->recordId)
                        ->delete();

                    $this->lastShareUrl = null;

                    Notification::make()
                        ->title($deleted === 1 ? '1 link revocato' : "{$deleted} link revocati")
                        ->success()
                        ->send();
                }),

            Action::make('edit')
                ->label('Modifica')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->url(fn (): string => $this->widgetResourceUrl('edit')),
        ];
    }

    /**
     * Revoca un singolo link condiviso dal suo ID.
     * Chiamato dal partial Blade via wire:click.
     */
    public function revokeShare(int $shareId): void
    {
        $deleted = DashboardWidgetShare::query()
            ->where('id', $shareId)
            ->where('dashboard_widget_id', $this->recordId)
            ->delete();

        if ($deleted) {
            if ($this->lastShareUrl !== null) {
                // Azzera il banner se il link revocato era quello appena creato
                $this->lastShareUrl = null;
            }

            Notification::make()
                ->title('Link revocato')
                ->success()
                ->send();
        }
    }

    public function getTitle(): string|Htmlable
    {
        return $this->widgetTitle ?? ('Widget #'.$this->recordId);
    }

    public function getSubheading(): string|Htmlable|null
    {
        if ($this->drillFilters !== []) {
            return 'Dettaglio dei record · '.collect($this->drillFilters)
                ->map(fn (array $filter): string => ($filter['label'] ?? $filter['expr'] ?? '?')
                    .' = '.Str::limit((string) ($filter['value'] ?? 'NULL'), 60))
                ->implode('   ·   ');
        }

        return filled($this->masterFilterField) ? $this->masterFilterField : null;
    }

    public function getBreadcrumbs(): array
    {
        return [
            DashboardWidgetResource::getUrl() => 'Dashboard Widget',
            '#' => $this->getTitle(),
        ];
    }
}
