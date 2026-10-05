<?php

namespace App\Filament\Resources\SchemaLegends\Pages;

use App\Exports\WidgetDatasetExport;
use App\Filament\Resources\SchemaLegends\SchemaLegendResource;
use App\Models\SchemaLegend;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ViewSchemaLegend extends ViewRecord
{
    protected static string $resource = SchemaLegendResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadTableExcel')
                ->label('Download Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function () {
                    /** @var SchemaLegend $record */
                    $record = $this->record;

                    $headings = $record->columns()->orderBy('position')->pluck('name')->all();

                    $rows = DB::connection($record->connection)
                        ->table($record->table_name)
                        ->get()
                        ->map(fn (object $row): array => (array) $row)
                        ->all();

                    $name = Str::slug($record->table_name.'-dati') ?: 'dati';

                    return Excel::download(
                        new WidgetDatasetExport($headings, $rows, $record->table_name),
                        $name.'-'.now()->format('Ymd-His').'.xlsx',
                    );
                }),

            EditAction::make()
                ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
        ];
    }

    public function getTitle(): string
    {
        return __('filament/admin/view_schema_legend.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin/view_schema_legend.title');
    }
}
