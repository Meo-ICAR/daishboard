<?php

namespace Tests\Feature;

use App\Models\SchemaLegend;
use App\Models\SchemaLegendColumn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SchemaLegendSyncHintsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_date_fields_scope_counts_typed_columns_without_a_semantic_category(): void
    {
        $legend = SchemaLegend::create(['connection' => 'dbai', 'table_name' => 'x', 'label' => 'X']);
        $legend->columns()->createMany([
            ['name' => 'created_at', 'data_type' => 'datetime', 'position' => 1, 'nullable' => true],
            ['name' => 'giorno', 'data_type' => 'date', 'position' => 2, 'nullable' => true],
            ['name' => 'nota', 'data_type' => 'varchar', 'position' => 3, 'nullable' => true],
        ]);

        $this->assertSame(2, $legend->columns()->dateFields()->count());

        $this->assertTrue($legend->columns()->where('name', 'giorno')->first()->isDate());
        $this->assertFalse($legend->columns()->where('name', 'nota')->first()->isDate());
    }

    public function test_profile_lookup_hint_links_a_denormalised_string_column(): void
    {
        // `patients.centro` è una stringa senza foreign key: senza hint non
        // verrebbe collegata. `centers` è una piccola tabella di codifica reale.
        config(['data_navigator.profiles.hiv.lookups' => [
            'patients.centro' => ['table' => 'centers', 'key' => 'center'],
        ]]);

        Artisan::call('legend:sync', ['tables' => ['patients']]);

        $column = SchemaLegendColumn::query()
            ->whereHas('legend', fn ($q) => $q->where('table_name', 'patients'))
            ->where('name', 'centro')
            ->firstOrFail();

        $this->assertSame('centers', $column->lookup_table);
        $this->assertSame('center', $column->lookup_key);
        $this->assertNotEmpty($column->lookup_values);
        // La tabella di codifica finisce anche nel catalogo lookup e sul pivot.
        $this->assertTrue($column->lookupTables()->where('table_name', 'centers')->exists());
    }

    public function test_full_sync_prunes_legends_no_longer_among_the_profile_tables(): void
    {
        // Legenda di una tabella che non è (più) fra le tabelle principali del profilo.
        $orphan = SchemaLegend::create([
            'connection' => 'dbai',
            'database' => 'hassisdadmin',
            'table_name' => 'centers',
            'label' => 'Centers',
        ]);
        $orphan->columns()->create(['name' => 'center', 'data_type' => 'varchar', 'position' => 1, 'nullable' => false]);

        Artisan::call('legend:sync');

        $this->assertDatabaseMissing('schema_legends', ['id' => $orphan->id]);
        $this->assertDatabaseMissing('schema_legend_columns', ['schema_legend_id' => $orphan->id]);
        $this->assertTrue(SchemaLegend::query()->where('table_name', 'patients')->exists());
    }

    public function test_targeted_sync_with_a_tables_argument_does_not_prune(): void
    {
        Artisan::call('legend:sync');
        $before = SchemaLegend::query()->count();

        Artisan::call('legend:sync', ['tables' => ['patients']]);

        $this->assertSame($before, SchemaLegend::query()->count());
    }
}
