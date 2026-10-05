<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

/**
 * Grafico "muto": riceve righe e colonne già calcolate dalla pagina e disegna
 * un chart Chart.js del tipo richiesto. Nessun accesso al database.
 */
class DashboardWidgetChart extends ChartWidget
{
    protected static bool $isLazy = false;

    /** Tipi di grafico supportati (chiavi Chart.js). */
    public const SUPPORTED_TYPES = ['bar', 'line', 'pie', 'doughnut', 'polarArea', 'radar'];

    /** Etichette IT dei tipi supportati. */
    public const TYPE_LABELS = [
        'bar' => 'Barre',
        'line' => 'Linee',
        'pie' => 'Torta',
        'doughnut' => 'Ciambella',
        'polarArea' => 'Area polare',
        'radar' => 'Radar',
    ];

    /** Normalizzazione di dashboard_widget->type verso una chiave Chart.js. */
    private const TYPE_ALIASES = [
        'bar' => 'bar', 'bar_chart' => 'bar', 'column' => 'bar', 'histogram' => 'bar',
        'line' => 'line', 'line_chart' => 'line', 'area' => 'line', 'area_chart' => 'line', 'trend' => 'line',
        'pie' => 'pie', 'pie_chart' => 'pie',
        'doughnut' => 'doughnut', 'doughnut_chart' => 'doughnut', 'donut' => 'doughnut',
        'polararea' => 'polarArea', 'polar_area' => 'polarArea', 'polar' => 'polarArea',
        'radar' => 'radar', 'radar_chart' => 'radar',
    ];

    /**
     * Palette per distinguere le serie / i settori Y del grafico.
     *
     * @var list<string>
     */
    public const PALETTE = [
        '#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#7c3aed',
        '#0891b2', '#db2777', '#65a30d', '#ea580c', '#4f46e5',
        '#0d9488', '#9333ea',
    ];

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public ?string $labelColumn = null;

    /**
     * Colonne valore (asse Y). Ogni colonna è una serie con un colore diverso.
     *
     * @var list<string>
     */
    public array $valueColumns = [];

    public ?string $chartType = null;

    public ?string $chartHeading = null;

    /** Altezza massima del grafico (es. "16rem"); passata dalla pagina che lo incorpora. */
    public ?string $maxHeight = null;

    /**
     * Normalizza una stringa tipo ("Line Chart", "bar_chart", "pie"...) verso
     * una chiave Chart.js supportata; fallback a "bar".
     */
    public static function resolveType(?string $type): string
    {
        $key = str_replace([' ', '-'], '_', strtolower(trim((string) $type)));

        if (isset(self::TYPE_ALIASES[$key])) {
            return self::TYPE_ALIASES[$key];
        }

        return in_array($type, self::SUPPORTED_TYPES, true) ? (string) $type : 'bar';
    }

    protected function getType(): string
    {
        return in_array($this->chartType, self::SUPPORTED_TYPES, true) ? $this->chartType : 'bar';
    }

    public function getHeading(): ?string
    {
        return $this->chartHeading;
    }

    protected function getData(): array
    {
        $valueColumns = array_values(array_filter(
            $this->valueColumns,
            static fn ($column): bool => is_string($column) && $column !== '',
        ));

        if ($this->labelColumn === null || $valueColumns === [] || $this->rows === []) {
            return ['datasets' => [], 'labels' => []];
        }

        $labels = array_map(fn (array $row): string => (string) ($row[$this->labelColumn] ?? ''), $this->rows);
        $type = $this->getType();

        // Con una sola serie Y, i grafici "per categoria" colorano ogni
        // elemento dell'asse X (barra / settore / vertice) con un colore diverso.
        $colorByCategory = count($valueColumns) === 1
            && in_array($type, ['bar', 'pie', 'doughnut', 'polarArea', 'radar'], true);

        $datasets = [];

        foreach (array_values($valueColumns) as $index => $column) {
            $data = array_map(function (array $row) use ($column): float {
                $raw = $row[$column] ?? null;

                return is_numeric($raw) ? (float) $raw : 0.0;
            }, $this->rows);

            $seriesColor = self::PALETTE[$index % count(self::PALETTE)];

            if ($colorByCategory) {
                $categoryColors = array_map(
                    fn (int $i): string => self::PALETTE[$i % count(self::PALETTE)],
                    array_keys($data),
                );

                $datasets[] = $type === 'radar'
                    ? [
                        'label' => $column,
                        'data' => $data,
                        'backgroundColor' => $this->rgba($seriesColor, 0.2),
                        'borderColor' => $seriesColor,
                        'pointBackgroundColor' => $categoryColors,
                        'pointBorderColor' => $categoryColors,
                    ]
                    : [
                        'label' => $column,
                        'data' => $data,
                        'backgroundColor' => $categoryColors,
                        'borderColor' => $categoryColors,
                    ];

                continue;
            }

            $datasets[] = [
                'label' => $column,
                'data' => $data,
                'backgroundColor' => $type === 'line' ? $this->rgba($seriesColor, 0.15) : $seriesColor,
                'borderColor' => $seriesColor,
            ];
        }

        return ['datasets' => $datasets, 'labels' => $labels];
    }

    protected function rgba(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');

        $r = (int) hexdec(substr($hex, 0, 2));
        $g = (int) hexdec(substr($hex, 2, 2));
        $b = (int) hexdec(substr($hex, 4, 2));

        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }
}
