<?php

namespace Tests\Feature;

use App\Filament\Resources\LookupTables\Pages\ViewLookupTable;
use App\Filament\Resources\LookupTables\RelationManagers\ColumnsRelationManager;
use App\Models\LookupTable;
use App\Models\SchemaLegend;
use App\Models\SchemaLegendColumn;
use App\Models\User;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

class LookupTableLinkingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        Artisan::call('legend:sync');
    }

    public function test_it_catalogues_lookup_tables_and_excludes_entities(): void
    {
        $this->assertGreaterThanOrEqual(30, LookupTable::count());
        $this->assertFalse(LookupTable::where('table_name', 'patients')->exists());
        $this->assertFalse(LookupTable::where('table_name', 'cache')->exists());

        $fumos = LookupTable::where('table_name', 'fumos')->firstOrFail();
        $this->assertTrue($fumos->is_dictionary);
        $this->assertNotEmpty($fumos->values);
        $this->assertSame(['value', 'label'], array_keys($fumos->values[0]));
    }

    public function test_it_auto_links_lookups_to_the_fields_that_reference_them(): void
    {
        $fumos = LookupTable::where('table_name', 'fumos')->firstOrFail();

        $this->assertGreaterThanOrEqual(2, $fumos->columns()->count());

        $patientsFumo = SchemaLegendColumn::query()
            ->whereHas('legend', fn ($q) => $q->where('table_name', 'patients'))
            ->where('name', 'fumo_id')
            ->firstOrFail();

        $this->assertTrue($patientsFumo->lookupTables()->whereKey($fumos->id)->exists());
    }

    public function test_a_lookup_can_be_linked_and_unlinked_to_a_field_via_the_relation_manager(): void
    {
        $etnias = LookupTable::where('table_name', 'etnias')->firstOrFail();
        $column = SchemaLegendColumn::query()
            ->whereHas('legend', fn ($q) => $q->where('table_name', 'patient_visits'))
            ->where('name', 'CD4')
            ->firstOrFail();

        $etnias->columns()->detach($column->id);
        $before = $etnias->columns()->count();

        Livewire::test(ColumnsRelationManager::class, [
            'ownerRecord' => $etnias,
            'pageClass' => ViewLookupTable::class,
        ])
            ->callTableAction(AttachAction::class, data: ['recordId' => [$column->id]])
            ->assertHasNoTableActionErrors();

        $this->assertSame($before + 1, $etnias->columns()->count());
        $this->assertTrue($etnias->columns()->whereKey($column->id)->exists());

        // Un nuovo sync non deve rimuovere il collegamento manuale.
        Artisan::call('legend:sync');
        $this->assertTrue($etnias->fresh()->columns()->whereKey($column->id)->exists());

        Livewire::test(ColumnsRelationManager::class, [
            'ownerRecord' => $etnias->fresh(),
            'pageClass' => ViewLookupTable::class,
        ])
            ->callTableAction(DetachAction::class, $column->id)
            ->assertHasNoTableActionErrors();

        $this->assertFalse($etnias->fresh()->columns()->whereKey($column->id)->exists());
    }

    public function test_relation_manager_shows_qualified_field_titles(): void
    {
        $etnias = LookupTable::where('table_name', 'etnias')->firstOrFail();
        $column = SchemaLegendColumn::query()
            ->whereHas('legend', fn ($q) => $q->where('table_name', 'patients'))
            ->where('name', 'etnia_id')
            ->firstOrFail();

        $manager = Livewire::test(ColumnsRelationManager::class, [
            'ownerRecord' => $etnias,
            'pageClass' => ViewLookupTable::class,
        ])->instance();

        $this->assertSame('patients.etnia_id', $manager->getRecordTitle($column));
    }

    public function test_legend_still_populated_for_patients_and_patient_visits(): void
    {
        $patients = SchemaLegend::where('table_name', 'patients')->firstOrFail();

        $this->assertGreaterThan(150, $patients->columns_count);
        $this->assertTrue($patients->columns()->whereNotNull('date_category')->exists());
        $this->assertTrue($patients->columns()->whereNotNull('lookup_table')->exists());
    }
}
