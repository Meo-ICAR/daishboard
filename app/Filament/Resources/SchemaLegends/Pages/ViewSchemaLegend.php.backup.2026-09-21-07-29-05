<?php

namespace App\Filament\Resources\SchemaLegends\Pages;

use App\Exports\WidgetDatasetExport;
use App\Filament\Resources\SchemaLegends\SchemaLegendResource;
use App\Models\SchemaLegend;
use App\Models\SchemaLegendColumn;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ViewSchemaLegend extends ViewRecord
{
    protected static string $resource = SchemaLegendResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Scarica Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => $this->record->columns()->exists())
                ->action(function () {
                    /** @var SchemaLegend $record */
                    $record = $this->record;

                    $headings = ['#', 'Campo', 'Tipo', 'Nullable', 'Commento', 'Categoria data', 'Range date / Valori lookup', 'Lookup'];

                    $rows = $record->columns()
                        ->with('lookupTables:id,table_name')
                        ->orderBy('position')
                        ->get()
                        ->map(fn (SchemaLegendColumn $column): array => [
                            '#' => $column->position,
                            'Campo' => $column->name,
                            'Tipo' => $column->data_type,
                            'Nullable' => $column->nullable ? 'sì' : 'no',
                            'Commento' => $column->comment,
                            'Categoria data' => $column->date_category,
                            'Range date / Valori lookup' => $column->summary(),
                            'Lookup' => $column->lookup_table ?? $column->lookupTables->pluck('table_name')->first(),
                        ])
                        ->all();

                    $name = Str::slug($record->table_name.'-legenda') ?: 'legenda';

                    return Excel::download(
                        new WidgetDatasetExport($headings, $rows, $record->table_name),
                        $name.'-'.now()->format('Ymd-His').'.xlsx',
                    );
                }),

            EditAction::make()
                ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
        ];
    }
}
