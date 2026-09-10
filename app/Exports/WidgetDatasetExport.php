<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Esporta in Excel una tabella generica: intestazioni + righe associative
 * (chiavi = intestazioni). Usato per il risultato di un DashboardWidget, per i
 * campi di una legenda schema e per i valori di una tabella di codifica.
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
