<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Esporta in Excel il risultato (già filtrato) della query di un DashboardWidget.
 * Riceve colonne e righe così come calcolate da WidgetDatasetRunner.
 */
class WidgetDatasetExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  list<string>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(
        protected array $columns,
        protected array $rows,
        protected string $title = 'Dati',
    ) {}

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->columns;
    }

    /**
     * @return array<int, list<mixed>>
     */
    public function array(): array
    {
        return array_map(
            fn (array $row): array => array_map(
                static fn (string $column) => $row[$column] ?? null,
                $this->columns,
            ),
            array_values($this->rows),
        );
    }

    public function title(): string
    {
        return mb_substr($this->title, 0, 31) ?: 'Dati';
    }
}
