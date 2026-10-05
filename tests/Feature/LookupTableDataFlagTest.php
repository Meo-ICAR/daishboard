<?php

namespace Tests\Feature;

use App\Filament\Resources\LookupTables\Pages\ListLookupTables;
use App\Models\LookupTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

class LookupTableDataFlagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('legend:sync');
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'company_id' => null]);
    }

    public function test_lookup_tables_default_to_not_being_data_tables(): void
    {
        $this->assertTrue(LookupTable::query()->where('is_data_table', false)->exists());
        $this->assertFalse(LookupTable::query()->where('is_data_table', true)->exists());
    }

    public function test_super_admin_can_toggle_the_data_table_flag(): void
    {
        $this->actingAs($this->superAdmin());

        $lookup = LookupTable::query()->firstOrFail();

        Livewire::test(ListLookupTables::class)
            ->call('updateTableColumnState', 'is_data_table', $lookup->getKey(), true);

        $this->assertTrue($lookup->fresh()->is_data_table);
    }

    public function test_normal_user_cannot_toggle_the_data_table_flag(): void
    {
        $this->actingAs(User::factory()->create());

        $lookup = LookupTable::query()->firstOrFail();

        Livewire::test(ListLookupTables::class)
            ->call('updateTableColumnState', 'is_data_table', $lookup->getKey(), true);

        $this->assertFalse($lookup->fresh()->is_data_table);
    }

    public function test_manual_data_table_flag_survives_a_legend_sync(): void
    {
        $lookup = LookupTable::query()->firstOrFail();
        $lookup->update(['is_data_table' => true]);

        Artisan::call('legend:sync');

        $this->assertTrue($lookup->fresh()->is_data_table);
    }

    public function test_data_table_filter_narrows_the_list(): void
    {
        $this->actingAs($this->superAdmin());

        $dataTable = LookupTable::query()->firstOrFail();
        $dataTable->update(['is_data_table' => true]);

        Livewire::test(ListLookupTables::class)
            ->filterTable('is_data_table', true)
            ->assertCanSeeTableRecords([$dataTable])
            ->assertCanNotSeeTableRecords(
                LookupTable::query()->whereKeyNot($dataTable->getKey())->get(),
            );
    }
}
