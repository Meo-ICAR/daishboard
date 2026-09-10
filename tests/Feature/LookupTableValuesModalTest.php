<?php

namespace Tests\Feature;

use App\Filament\Resources\LookupTables\Pages\ViewLookupTable;
use App\Models\LookupTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

class LookupTableValuesModalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('legend:sync');
        $this->actingAs(User::factory()->create());
    }

    public function test_the_values_action_mounts_a_filament_schema_without_errors(): void
    {
        // Tabella con colonna etichetta: esercita il ramo a 2 colonne dello schema.
        $users = LookupTable::query()->where('table_name', 'users')->firstOrFail();
        $this->assertNotNull($users->label_column);

        Livewire::test(ViewLookupTable::class, ['record' => $users->getKey()])
            ->assertActionVisible('liveValues')
            ->mountAction('liveValues')
            ->assertActionMounted('liveValues')
            ->assertHasNoActionErrors();
    }

    public function test_the_values_action_mounts_for_a_single_column_dictionary(): void
    {
        $etnias = LookupTable::query()->where('table_name', 'etnias')->firstOrFail();
        $this->assertNull($etnias->label_column);

        Livewire::test(ViewLookupTable::class, ['record' => $etnias->getKey()])
            ->mountAction('liveValues')
            ->assertActionMounted('liveValues')
            ->assertHasNoActionErrors();
    }
}
