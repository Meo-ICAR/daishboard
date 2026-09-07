<?php

namespace App\Filament\Pages;

use App\Filament\Resources\DashboardWidgets\DashboardWidgetResource;
use App\Filament\Widgets\DashboardWidgetChart;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Project;
use App\Services\WidgetDatasetRunner;
use App\Support\CompanyScope;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

use Throwable;

/**
 * Dashboard dei grafici: raggruppa tutti i grafici master. Cliccando un master
 * si ricostruisce la dashboard con i suoi grafici figli (?master={id}).
 * I filtri di coorte provengono dallo studio in corso dell'utente (Project).
 */
class DashboardChartsOverview extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static ?string $navigationLabel = 'Cruscotto';

    protected static ?string $title = 'Dashboard grafici';

    protected string $view = 'filament.pages.dashboard-charts-overview';

    /** Master corrente: se valorizzato mostra i suoi figli. */
    #[Url]
    public ?int $master = null;

    /** Dashboard selezionata (scope della vista); default: la prima. */
    #[Url]
    public ?int $dashboardId = null;

    /**
     * Filtri di coorte dello studio in corso.
     *
     * @var array<int, array{column: ?string, from: ?string, to: ?string}>
     */
    public array $projectFilters = [];

    /**
     * Catalogo colonne data filtrabili (unione dei widget mostrati).
     *
     * @var array<string, array{label: string, presets: array<int, array{value: string, label: string, from: ?string, to: ?string}>}>
     */
    public array $dateFilterCatalog = [];

    /**
     * Grafici da mostrare.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $charts = [];

    public ?string $masterTitle = null;

    public function mount(): void
    {
        $this->projectFilters = Project::currentFor(auth()->id())?->cohortFilters() ?? [];
        $this->resolveDashboardScope();
        $this->rebuild();
    }

    /**
     * In vista master lo scope segue la dashboard del master; altrimenti si usa
     * la dashboard scelta, con default sulla prima disponibile.
     */
    protected function resolveDashboardScope(): void
    {
        if ($this->master !== null) {
            $masterDashboardId = DashboardWidget::whereKey($this->master)->value('dashboard_id');

            if ($masterDashboardId !== null) {
                $this->dashboardId = (int) $masterDashboardId;

                return;
            }

            $this->master = null;
        }

        if ($this->dashboardId === null || ! Dashboard::whereKey($this->dashboardId)->exists()) {
            $this->dashboardId = Dashboard::query()->orderBy('order')->orderBy('id')->value('id');
        }
    }

    public function updatedDashboardId(): void
    {
        $this->master = null;
        $this->resolveDashboardScope();
        $this->rebuild();
    }

    protected function rebuild(): void
    {
        $runner = app(WidgetDatasetRunner::class);
        $widgets = $this->widgetsToShow();

        $this->masterTitle = $this->master !== null
            ? (DashboardWidget::whereKey($this->master)->value('title') ?? ('Widget #'.$this->master))
            : null;

        $this->dateFilterCatalog = [];
        $this->charts = [];

        foreach ($widgets as $widget) {
            foreach ($runner->dateFilterMetadata($widget->query) as $key => $meta) {
                $this->dateFilterCatalog[$key] ??= ['label' => $meta['label'], 'presets' => $meta['presets']];
            }

            $result = $runner->run($widget->query, $this->projectFilters);
            $columns = $result['columns'];
            $numeric = $result['numericColumns'];

            $label = collect($columns)->first(fn (string $c): bool => ! in_array($c, $numeric, true))
                ?? ($columns[0] ?? null);

            // Sull'overview una sola serie Y: la prima colonna numerica diversa
            // dalla categoria. Così ogni barra/settore prende un colore diverso.
            $value = collect($numeric)->first(fn (string $c): bool => $c !== $label);
            $values = $value !== null ? [$value] : [];

            $hasChildren = $widget->detailWidgets()->exists();

            $this->charts[] = [
                'id' => $widget->getKey(),
                'title' => $widget->title ?? ('Widget #'.$widget->getKey()),
                'type' => DashboardWidgetChart::resolveType($widget->type),
                'labelColumn' => $label,
                'valueColumns' => $values,
                'rows' => $result['rows'],
                'error' => $result['error'],
                'isMaster' => $this->master !== null && (int) $widget->getKey() === $this->master,
                'hasChildren' => $hasChildren,
                'drillUrl' => $hasChildren
                    ? static::getUrl(['master' => $widget->getKey(), 'dashboardId' => $this->dashboardId])
                    : DashboardWidgetResource::getUrl('view', ['record' => $widget->getKey()]),
                'viewUrl' => DashboardWidgetResource::getUrl('view', ['record' => $widget->getKey()]),
            ];
        }
    }

    /**
     * @return Collection<int, DashboardWidget>
     */
    protected function widgetsToShow()
    {
        if ($this->master !== null) {
            $master = DashboardWidget::query()->find($this->master);

            if ($master === null) {
                $this->master = null;

                return $this->topLevelMasters();
            }

            $children = $master->detailWidgets()->orderBy('order')->orderBy('id')->get();

            return collect([$master])->concat($children);
        }

        return $this->topLevelMasters();
    }

    /**
     * @return Collection<int, DashboardWidget>
     */
    protected function topLevelMasters()
    {
        return CompanyScope::byOwner(DashboardWidget::query())
            ->whereNull('master_widget_id')
            ->where('is_active', true)
            ->when($this->dashboardId !== null, fn ($query) => $query->where('dashboard_id', $this->dashboardId))
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }

    public function studyFilterDescription(): ?string
    {
        if ($this->activeProjectFilters() === []) {
            return null;
        }

        $parts = array_map(function (array $filter): string {
            $label = $this->dateFilterCatalog[$filter['column']]['label'] ?? $filter['column'];

            $range = match (true) {
                $filter['from'] !== null && $filter['to'] !== null => "dal {$filter['from']} al {$filter['to']}",
                $filter['from'] !== null => "dal {$filter['from']}",
                default => "fino al {$filter['to']}",
            };

            return "{$label} {$range}";
        }, $this->activeProjectFilters());

        return 'Studio in corso · '.implode('   ·   ', $parts);
    }

    /**
     * @return array<int, array{column: string, from: ?string, to: ?string}>
     */
    protected function activeProjectFilters(): array
    {
        return collect($this->projectFilters)
            ->filter(fn (array $f): bool => filled($f['column'] ?? null)
                && isset($this->dateFilterCatalog[$f['column']])
                && (($f['from'] ?? null) !== null || ($f['to'] ?? null) !== null))
            ->values()
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('dashboard')
                ->label(fn (): string => 'Dashboard: '.($this->dashboardTitle() ?? '—'))
                ->icon(Heroicon::OutlinedRectangleGroup)
                ->color('gray')
                ->visible(fn (): bool => $this->master === null && Dashboard::query()->count() > 1)
                ->fillForm(fn (): array => ['dashboardId' => $this->dashboardId])
                ->schema([
                    Select::make('dashboardId')
                        ->label('Dashboard')
                        ->options(fn (): array => Dashboard::query()
                            ->orderBy('order')->orderBy('id')
                            ->pluck('title', 'id')->all())
                        ->selectablePlaceholder(false)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->dashboardId = (int) $data['dashboardId'];
                    $this->master = null;
                    $this->resolveDashboardScope();
                    $this->rebuild();
                }),

            Action::make('studyFilters')
                ->label('Filtri studio')
                ->icon(Heroicon::OutlinedFunnel)
                ->badge(fn (): ?string => ($n = count($this->activeProjectFilters())) > 0 ? (string) $n : null)
                ->badgeColor('warning')
                ->visible(fn (): bool => filled($this->dateFilterCatalog))
                ->modalHeading('Filtri di coorte dello studio in corso')
                ->modalDescription('Applicati a tutti i grafici della dashboard e memorizzati nel tuo studio.')
                ->fillForm(fn (): array => [
                    'filters' => $this->projectFilters === []
                        ? [['column' => null, 'preset' => null, 'from' => null, 'to' => null]]
                        : array_map(fn (array $f): array => [
                            'column' => $f['column'] ?? null,
                            'preset' => null,
                            'from' => $f['from'] ?? null,
                            'to' => $f['to'] ?? null,
                        ], $this->projectFilters),
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
                                ->options(fn (): array => collect($this->dateFilterCatalog)->map(fn ($m) => $m['label'])->all())
                                ->live()
                                ->columnSpanFull(),
                            Select::make('preset')
                                ->label('Periodo')
                                ->placeholder('Intervallo personalizzato')
                                ->options(fn (Get $get): array => collect($this->dateFilterCatalog[$get('column')]['presets'] ?? [])
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
                ->action(fn (array $data) => $this->applyStudyFilters($data)),

            Action::make('clearStudyFilters')
                ->label('Rimuovi filtri')
                ->icon(Heroicon::OutlinedXMark)
                ->color('gray')
                ->visible(fn (): bool => $this->activeProjectFilters() !== [])
                ->action(function (): void {
                    $this->projectFilters = [];
                    Project::storeCurrentFilters(auth()->id(), []);
                    $this->rebuild();
                }),

            Action::make('allMasters')
                ->label('Tutti i master')
                ->icon(Heroicon::OutlinedSquares2x2)
                ->color('gray')
                ->visible(fn (): bool => $this->master !== null)
                ->url(fn (): string => static::getUrl(['dashboardId' => $this->dashboardId])),
        ];
    }

    protected function dashboardTitle(): ?string
    {
        return $this->dashboardId !== null
            ? Dashboard::whereKey($this->dashboardId)->value('title')
            : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function applyStudyFilters(array $data): void
    {
        $this->projectFilters = collect($data['filters'] ?? [])
            ->map(function (array $row): array {
                $column = filled($row['column'] ?? null) ? (string) $row['column'] : null;
                $preset = filled($row['preset'] ?? null) ? (string) $row['preset'] : null;

                if ($column !== null && $preset !== null) {
                    $resolved = collect($this->dateFilterCatalog[$column]['presets'] ?? [])
                        ->firstWhere('value', $preset);

                    $from = $this->normalizeDate($resolved['from'] ?? null);
                    $to = $this->normalizeDate($resolved['to'] ?? null);
                } else {
                    $from = $this->normalizeDate($row['from'] ?? null);
                    $to = $this->normalizeDate($row['to'] ?? null);
                }

                return ['column' => $column, 'from' => $from, 'to' => $to];
            })
            ->filter(fn (array $row): bool => $row['column'] !== null
                && isset($this->dateFilterCatalog[$row['column']])
                && ($row['from'] !== null || $row['to'] !== null))
            ->values()
            ->all();

        Project::storeCurrentFilters(auth()->id(), $this->projectFilters);
        $this->rebuild();
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

    public function getSubheading(): string|Htmlable|null
    {
        if ($this->masterTitle !== null) {
            return "Grafici figlio di: {$this->masterTitle}";
        }

        return collect([$this->dashboardTitle(), $this->studyFilterDescription()])
            ->filter()
            ->implode(' — ') ?: null;
    }
}
