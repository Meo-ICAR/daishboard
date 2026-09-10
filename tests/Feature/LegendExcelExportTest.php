<?php

namespace Tests\Feature;

use App\Exports\WidgetDatasetExport;
use App\Filament\Resources\LookupTables\Pages\ViewLookupTable;
use App\Filament\Resources\SchemaLegends\Pages\ViewSchemaLegend;
use App\Models\LookupTable;
use App\Models\SchemaLegend;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class LegendExcelExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('legend:sync');

        $this->actingAs(User::factory()->create());
        Excel::fake();
        $this->freezeTime();
    }

    public function test_schema_legend_view_downloads_its_fields_as_excel(): void
    {
        $legend = SchemaLegend::query()->where('table_name', 'patients')->firstOrFail();
        $stamp = now()->format('Ymd-His');

        Livewire::test(ViewSchemaLegend::class, ['record' => $legend->getKey()])
            ->assertActionVisible('exportExcel')
            ->callAction('exportExcel')
            ->assertHasNoActionErrors();

        Excel::assertDownloaded(
            "patients-legenda-{$stamp}.xlsx",
            fn (WidgetDatasetExport $export): bool => $export->headings() === [
                '#', 'Campo', 'Tipo', 'Nullable', 'Commento', 'Categoria data', 'Range date / Valori lookup', 'Lookup',
            ] && count($export->array()) === $legend->columns()->count(),
        );
    }

    public function test_lookup_table_view_downloads_its_values_as_excel(): void
    {
        $lookup = LookupTable::query()->where('table_name', 'fumos')->firstOrFail();
        $stamp = now()->format('Ymd-His');

        Livewire::test(ViewLookupTable::class, ['record' => $lookup->getKey()])
            ->assertActionVisible('exportExcel')
            ->callAction('exportExcel')
            ->assertHasNoActionErrors();

        Excel::assertDownloaded(
            "fumos-valori-{$stamp}.xlsx",
            fn (WidgetDatasetExport $export): bool => count($export->headings()) === 2
                && count($export->array()) === count($lookup->values),
        );
    }
}
