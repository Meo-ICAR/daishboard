<?php

namespace App\Filament\Resources\DashboardWidgets\Concerns;

use App\Filament\Resources\DashboardWidgets\DashboardWidgetResource;
use App\Models\DashboardWidget;
use App\Services\WidgetDatasetRunner;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;
use Throwable;

/**
 * Adapter Livewire attorno a {@see WidgetDatasetRunner}: esecuzione della query
 * di un DashboardWidget su DBAI con filtro data di coorte iniettato nel SQL
 * prima del raggruppamento. Condiviso da ViewDashboardWidget e ChartDashboardWidget.
 */
trait InteractsWithWidgetDataset
{
    #[Locked]
    public int|string $recordId;

    #[Locked]
    public ?string $widgetTitle = null;

    #[Locked]
    public ?string $widgetType = null;

    #[Locked]
    public ?string $widgetQuery = null;

    #[Locked]
    public ?int $widgetProjectId = null;

    /**
     * Filtri di coorte (data + valore) ereditati dallo studio del widget,
     * applicati alla query in AND prima del raggruppamento.
     *
     * @var array<int, array<string, mixed>>
     */
    #[Locked]
    public array $projectCohortFilters = [];

    /**
     * Colonne data della coorte su cui è possibile filtrare: [chiave => etichetta].
     *
     * @var array<string, string>
     */
    #[Locked]
    public array $dateFilterOptions = [];

    /**
     * Espressione SQL qualificata per ogni colonna data.
     *
     * @var array<string, array{expr: string}>
     */
    #[Locked]
    public array $dateFilterColumns = [];

    /**
     * Preset di intervallo per ogni colonna data filtrabile.
     *
     * @var array<string, array<int, array{value: string, label: string, from: ?string, to: ?string}>>
     */
    #[Locked]
    public array $datePresetCatalog = [];

    /**
     * Filtri data attivi sulla coorte, combinati in AND.
     *
     * @var array<int, array{column: ?string, preset: ?string, from: ?string, to: ?string}>
     */
    public array $dateFilters = [];

    /** @var list<string> */
    public array $queryColumns = [];

    /** @var list<string> */
    public array $numericColumns = [];

    /** @var array<int, array<string, mixed>> */
    public array $queryRows = [];

    public ?string $errorMessage = null;

    protected function bootWidgetDataset(int|string $record): DashboardWidget
    {
        $widget = DashboardWidget::query()->with('project')->findOrFail($record);

        $this->recordId = $widget->getKey();
        $this->widgetTitle = $widget->title;
        $this->widgetType = $widget->type;
        $this->widgetQuery = $widget->query;
        $this->widgetProjectId = $widget->project_id;
        $this->projectCohortFilters = $widget->project?->cohortFilters() ?? [];

        $metadata = app(WidgetDatasetRunner::class)->dateFilterMetadata($widget->query);

        $this->dateFilterOptions = [];
        $this->dateFilterColumns = [];
        $this->datePresetCatalog = [];

        foreach ($metadata as $key => $meta) {
            $this->dateFilterOptions[$key] = $meta['label'];
            $this->dateFilterColumns[$key] = ['expr' => $meta['expr']];
            $this->datePresetCatalog[$key] = $meta['presets'];
        }

        return $widget;
    }

    public function runQuery(): void
    {
        $result = app(WidgetDatasetRunner::class)->run(
            $this->datasetQuery(),
            $this->cohortFilters(),
        );

        $this->queryColumns = $result['columns'];
        $this->queryRows = $result['rows'];
        $this->numericColumns = $result['numericColumns'];
        $this->errorMessage = $result['error'];

        $this->afterQueryRefreshed();
    }

    /**
     * Query effettivamente eseguita da runQuery(). Le pagine possono
     * sovrascriverla (es. ViewDashboardWidget per il drill-down generato).
     */
    protected function datasetQuery(): ?string
    {
        return $this->widgetQuery;
    }

    /** Hook invocato al termine di runQuery(). */
    protected function afterQueryRefreshed(): void {}

    protected function hasActiveDateFilter(): bool
    {
        return $this->activeDateFilters() !== [];
    }

    /**
     * Filtri applicati alla query: quelli dello studio del widget seguiti dai
     * filtri data impostati manualmente sulla pagina (tutti in AND).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function cohortFilters(): array
    {
        return [...$this->projectCohortFilters, ...$this->activeDateFilters()];
    }

    /**
     * @return array<int, array{column: string, preset: ?string, from: ?string, to: ?string}>
     */
    protected function activeDateFilters(): array
    {
        return collect($this->dateFilters)
            ->filter(fn (array $filter): bool => filled($filter['column'] ?? null)
                && isset($this->dateFilterColumns[$filter['column']])
                && (($filter['from'] ?? null) !== null || ($filter['to'] ?? null) !== null))
            ->map(fn (array $filter): array => [
                'column' => (string) $filter['column'],
                'preset' => $filter['preset'] ?? null,
                'from' => $filter['from'] ?? null,
                'to' => $filter['to'] ?? null,
            ])
            ->values()
            ->all();
    }

    protected function activeDateFilterDescription(): ?string
    {
        return app(WidgetDatasetRunner::class)->describeFilters(
            $this->widgetQuery,
            $this->cohortFilters(),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function applyDateFilter(array $data): void
    {
        $this->dateFilters = collect($data['filters'] ?? [])
            ->map(function (array $row): array {
                $column = filled($row['column'] ?? null) ? (string) $row['column'] : null;
                $preset = filled($row['preset'] ?? null) ? (string) $row['preset'] : null;

                if ($column !== null && $preset !== null) {
                    $resolved = collect($this->datePresetCatalog[$column] ?? [])
                        ->firstWhere('value', $preset);

                    $from = $this->normalizeDate($resolved['from'] ?? null);
                    $to = $this->normalizeDate($resolved['to'] ?? null);
                } else {
                    $from = $this->normalizeDate($row['from'] ?? null);
                    $to = $this->normalizeDate($row['to'] ?? null);
                }

                return ['column' => $column, 'preset' => $preset, 'from' => $from, 'to' => $to];
            })
            ->filter(fn (array $row): bool => $row['column'] !== null
                && isset($this->dateFilterColumns[$row['column']])
                && ($row['from'] !== null || $row['to'] !== null))
            ->values()
            ->all();

        $this->runQuery();
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Azioni header condivise per impostare e rimuovere i filtri data di coorte.
     *
     * @return array<int, Action>
     */
    protected function dateFilterHeaderActions(): array
    {
        return [
            Action::make('filterDates')
                ->label('Filtro coorte')
                ->icon(Heroicon::OutlinedFunnel)
                ->badge(fn (): ?string => ($count = count($this->activeDateFilters())) > 0 ? (string) $count : null)
                ->badgeColor('warning')
                ->visible(fn (): bool => filled($this->dateFilterOptions))
                ->modalHeading('Filtra la coorte per data')
                ->modalDescription('I filtri restringono i pazienti (in AND) prima del raggruppamento.')
                ->fillForm(fn (): array => [
                    'filters' => $this->dateFilters === []
                        ? [['column' => null, 'preset' => null, 'from' => null, 'to' => null]]
                        : array_map(fn (array $filter): array => [
                            'column' => $filter['column'],
                            'preset' => $filter['preset'],
                            'from' => $filter['preset'] !== null ? null : $filter['from'],
                            'to' => $filter['preset'] !== null ? null : $filter['to'],
                        ], $this->dateFilters),
                ])
                ->schema([
                    Repeater::make('filters')
                        ->hiddenLabel()
                        ->addActionLabel('Aggiungi filtro data')
                        ->defaultItems(1)
                        ->reorderable(false)
                        ->columns(2)
                        ->schema([
                            Select::make('column')
                                ->label('Colonna data')
                                ->options(fn (): array => $this->dateFilterOptions)
                                ->live()
                                ->columnSpanFull(),
                            Select::make('preset')
                                ->label('Periodo')
                                ->placeholder('Intervallo personalizzato')
                                ->options(fn (Get $get): array => collect($this->datePresetCatalog[$get('column')] ?? [])
                                    ->pluck('label', 'value')
                                    ->all())
                                ->live()
                                ->columnSpanFull(),
                            DatePicker::make('from')
                                ->label('Da')
                                ->native(false)
                                ->visible(fn (Get $get): bool => blank($get('preset'))),
                            DatePicker::make('to')
                                ->label('A')
                                ->native(false)
                                ->visible(fn (Get $get): bool => blank($get('preset')))
                                ->afterOrEqual('from'),
                        ]),
                ])
                ->action(fn (array $data) => $this->applyDateFilter($data)),

            Action::make('clearDateFilter')
                ->label('Rimuovi filtri')
                ->icon(Heroicon::OutlinedXMark)
                ->color('gray')
                ->visible(fn (): bool => $this->hasActiveDateFilter())
                ->action(function (): void {
                    $this->dateFilters = [];
                    $this->runQuery();
                }),
        ];
    }

    protected function widgetResourceUrl(string $page): string
    {
        return DashboardWidgetResource::getUrl($page, ['record' => $this->recordId]);
    }
}
