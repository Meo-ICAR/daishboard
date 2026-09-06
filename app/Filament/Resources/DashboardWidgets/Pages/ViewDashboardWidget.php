<?php

namespace App\Filament\Resources\DashboardWidgets\Pages;

use App\Filament\Resources\DashboardWidgets\DashboardWidgetResource;
use App\Models\DashboardWidget;
use Filament\Actions\Action;
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
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Throwable;

class ViewDashboardWidget extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = DashboardWidgetResource::class;

    protected string $view = 'filament.resources.dashboard-widgets.pages.view-dashboard-widget';

    /**
     * Solo la chiave del widget viene mantenuta lato Livewire: nessun model
     * Eloquent viene serializzato o ricaricato dal database a ogni interazione
     * con la tabella (ordinamento, paginazione, "esegui di nuovo").
     */
    #[Locked]
    public int|string $recordId;

    #[Locked]
    public ?string $widgetTitle = null;

    #[Locked]
    public ?string $widgetQuery = null;

    /**
     * Valore della prima colonna del master relativo alla riga da cui si è
     * arrivati (query string `MasterFilterField`). Mostrato come sottotitolo.
     */
    #[Url(as: 'MasterFilterField', keep: false)]
    public ?string $masterFilterField = null;

    /**
     * Widget figli che puntano a questo widget come master.
     *
     * @var array<int, array{id: int, title: ?string, column: ?string}>
     */
    #[Locked]
    public array $drilldowns = [];

    /**
     * Nomi delle colonne estratti dinamicamente dal primo record della query.
     *
     * @var list<string>
     */
    public array $queryColumns = [];

    /**
     * Sottoinsieme di queryColumns i cui valori sono tutti numerici.
     *
     * @var list<string>
     */
    public array $numericColumns = [];

    /**
     * Righe risultanti dalla query, come array associativi indicizzati.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $queryRows = [];

    /** Messaggio di errore in caso di query non valida o non eseguibile. */
    public ?string $errorMessage = null;

    public function mount(int|string $record): void
    {
        $widget = DashboardWidget::query()->findOrFail($record);

        $this->recordId = $widget->getKey();
        $this->widgetTitle = $widget->title;
        $this->widgetQuery = $widget->query;

        if ($this->masterFilterField === '') {
            $this->masterFilterField = null;
        }

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

    /**
     * Esegue la query SQL del widget sulla connessione DBAI.
     * Accetta solo statement SELECT per sicurezza.
     */
    public function runQuery(): void
    {
        $this->queryColumns = [];
        $this->numericColumns = [];
        $this->queryRows = [];
        $this->errorMessage = null;

        $query = trim((string) $this->widgetQuery);

        if ($query === '') {
            $this->errorMessage = 'Nessuna query definita per questo widget.';

            return;
        }

        if (! preg_match('/^\s*SELECT\b/i', $query)) {
            $this->errorMessage = 'Sono consentite solo istruzioni SELECT.';

            return;
        }

        try {
            $results = DB::connection('dbai')->select($query);
        } catch (Throwable $e) {
            $this->errorMessage = 'Errore nell\'esecuzione della query: '.$e->getMessage();

            return;
        }

        if ($results === []) {
            return;
        }

        $this->queryColumns = array_keys((array) $results[0]);
        $this->queryRows = array_values(array_map(
            static fn ($row): array => (array) $row,
            $results,
        ));

        $this->numericColumns = array_values(array_filter(
            $this->queryColumns,
            fn (string $column): bool => $this->columnIsNumeric($column),
        ));

        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (?string $sortColumn, ?string $sortDirection, int $page, int $recordsPerPage): LengthAwarePaginator {
                // Le colonne sono esposte come `col_<indice>`: risale al nome reale del campo.
                $sortField = filled($sortColumn)
                    ? ($this->queryColumns[(int) substr($sortColumn, 4)] ?? null)
                    : null;

                $rows = collect($this->queryRows)
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
            ->heading($this->widgetTitle ?? ('Widget #'.$this->recordId))
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
            }

            $columns[] = $column;
        }

        return $columns;
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
     * Una colonna è numerica se ha almeno un valore valorizzato e tutti i valori
     * non nulli sono numerici.
     */
    protected function columnIsNumeric(string $column): bool
    {
        $hasValue = false;

        foreach ($this->queryRows as $row) {
            $value = $row[$column] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            if (! is_numeric($value)) {
                return false;
            }

            $hasValue = true;
        }

        return $hasValue;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runQuery')
                ->label('Esegui di nuovo')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(fn () => $this->runQuery()),

            Action::make('edit')
                ->label('Modifica')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->url(DashboardWidgetResource::getUrl('edit', ['record' => $this->recordId])),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return $this->widgetTitle ?? ('Widget #'.$this->recordId);
    }

    public function getSubheading(): string|Htmlable|null
    {
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
