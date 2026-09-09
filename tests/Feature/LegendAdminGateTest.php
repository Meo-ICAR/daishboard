<?php

namespace Tests\Feature;

use App\Filament\Resources\LookupTables\LookupTableResource;
use App\Filament\Resources\LookupTables\Pages\ListLookupTables;
use App\Filament\Resources\SchemaLegends\Pages\ListSchemaLegends;
use App\Filament\Resources\SchemaLegends\SchemaLegendResource;
use App\Models\LookupTable;
use App\Models\SchemaLegend;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

class LegendAdminGateTest extends TestCase
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

    public function test_normal_user_cannot_see_the_sync_actions_or_edit(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ListLookupTables::class)->assertActionHidden('sync');
        Livewire::test(ListSchemaLegends::class)->assertActionHidden('sync');

        Livewire::test(ListLookupTables::class)
            ->assertTableActionHidden('edit', LookupTable::query()->firstOrFail());
        Livewire::test(ListSchemaLegends::class)
            ->assertTableActionHidden('edit', SchemaLegend::query()->firstOrFail());

        $this->assertFalse(LookupTableResource::canEdit(LookupTable::query()->firstOrFail()));
        $this->assertFalse(SchemaLegendResource::canEdit(SchemaLegend::query()->firstOrFail()));
    }

    public function test_super_admin_can_see_the_sync_actions_and_edit(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ListLookupTables::class)->assertActionVisible('sync');
        Livewire::test(ListSchemaLegends::class)->assertActionVisible('sync');

        Livewire::test(ListLookupTables::class)
            ->assertTableActionVisible('edit', LookupTable::query()->firstOrFail());
        Livewire::test(ListSchemaLegends::class)
            ->assertTableActionVisible('edit', SchemaLegend::query()->firstOrFail());

        $this->assertTrue(LookupTableResource::canEdit(LookupTable::query()->firstOrFail()));
        $this->assertTrue(SchemaLegendResource::canEdit(SchemaLegend::query()->firstOrFail()));
    }
}
