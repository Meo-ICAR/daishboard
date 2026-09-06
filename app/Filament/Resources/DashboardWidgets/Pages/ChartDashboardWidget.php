<?php

namespace App\Filament\Resources\DashboardWidgets\Pages;

use App\Filament\Resources\DashboardWidgets\Concerns\InteractsWithWidgetDataset;
use App\Filament\Resources\DashboardWidgets\DashboardWidgetResource;
use App\Filament\Widgets\DashboardWidgetChart;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ChartDashboardWidget extends Page
{
    use InteractsWithWidgetDataset;

    protected static string $resource = DashboardWidgetResource::class;

    protected string $view = 'filament.resources.dashboard-widgets.pages.chart-dashboard-widget';

    /** Tipo di grafico corrente (chiave Chart.js). Default da dashboard_widget->type. */
    public ?string $chartType = null;

    /** Colonna categoria (asse X). */
    public ?string $labelColumn = null;

    /**
     * Colonne valore (asse Y): ogni colonna è una serie con un colore diverso.
     *
     * @var list<string>
     */
    public array $valueColumns = [];

    public function mount(int|string $record): void
    {
        $this->bootWidgetDataset($record);
        $this->runQuery();

        $this->chartType ??= DashboardWidgetChart::resolveType($this->widgetType);
        $this->initColumnSelection();
    }

    protected function afterQueryRefreshed(): void
    {
        $this->initColumnSelection();
    }

    /**
     * Sceglie categoria e valori di default (e li riallinea se non più presenti
     * dopo un cambio di filtro).
     */
    protected function initColumnSelection(): void
    {
        if ($this->queryColumns === []) {
            return;
        }

        if ($this->labelColumn === null || ! in_array($this->labelColumn, $this->queryColumns, true)) {
            $this->labelColumn = collect($this->queryColumns)
                ->first(fn (string $column): bool => ! in_array($column, $this->numericColumns, true))
                ?? $this->queryColumns[0];
        }

        $this->valueColumns = array_values(array_filter(
            $this->valueColumns,
            fn ($column): bool => in_array($column, $this->queryColumns, true),
        ));

        if ($this->valueColumns === []) {
            $default = collect($this->numericColumns)->first(fn (string $c): bool => $c !== $this->labelColumn)
                ?? collect($this->queryColumns)->first(fn (string $c): bool => $c !== $this->labelColumn)
                ?? $this->queryColumns[0];

            $this->valueColumns = [$default];
        }
    }

    /** @return array<string, string> */
    protected function yColumnOptions(): array
    {
        $columns = $this->numericColumns !== [] ? $this->numericColumns : $this->queryColumns;

        return array_combine($columns, $columns);
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->dateFilterHeaderActions(),

            Action::make('configureChart')
                ->label('Grafico')
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->visible(fn (): bool => $this->queryColumns !== [])
                ->modalHeading('Configura il grafico')
                ->modalSubmitActionLabel('Applica')
                ->fillForm(fn (): array => [
                    'chartType' => $this->chartType,
                    'labelColumn' => $this->labelColumn,
                    'valueColumns' => $this->valueColumns,
                ])
                ->schema([
                    Select::make('chartType')
                        ->label('Tipo di grafico')
                        ->options(DashboardWidgetChart::TYPE_LABELS)
                        ->required(),
                    Select::make('labelColumn')
                        ->label('Categoria (asse X)')
                        ->options(fn (): array => array_combine($this->queryColumns, $this->queryColumns))
                        ->required(),
                    Select::make('valueColumns')
                        ->label('Valori (asse Y)')
                        ->helperText('Ogni colonna scelta è una serie con un colore diverso.')
                        ->options(fn (): array => $this->yColumnOptions())
                        ->multiple()
                        ->minItems(1)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->chartType = DashboardWidgetChart::resolveType($data['chartType']);

                    if (in_array($data['labelColumn'], $this->queryColumns, true)) {
                        $this->labelColumn = $data['labelColumn'];
                    }

                    $chosen = array_values(array_filter(
                        (array) ($data['valueColumns'] ?? []),
                        fn ($column): bool => in_array($column, $this->queryColumns, true),
                    ));

                    if ($chosen !== []) {
                        $this->valueColumns = $chosen;
                    }
                }),

            Action::make('runQuery')
                ->label('Esegui di nuovo')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(fn () => $this->runQuery()),

            Action::make('table')
                ->label('Tabella')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->url(fn (): string => $this->widgetResourceUrl('view')),

            Action::make('edit')
                ->label('Modifica')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->url(fn (): string => $this->widgetResourceUrl('edit')),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return $this->widgetTitle ?? ('Widget #'.$this->recordId);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->activeDateFilterDescription();
    }

    public function getBreadcrumbs(): array
    {
        return [
            DashboardWidgetResource::getUrl() => 'Dashboard Widget',
            $this->widgetResourceUrl('view') => $this->widgetTitle ?? ('Widget #'.$this->recordId),
            '#' => 'Grafico',
        ];
    }
}
