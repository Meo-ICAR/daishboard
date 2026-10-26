<?php

namespace Tests\Feature;

use App\Filament\Resources\Dashboards\Pages\ManageDashboards;
use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardResourceItalianTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_locale_is_italian(): void
    {
        $this->assertSame('it', app()->getLocale());
    }

    public function test_dashboards_list_renders_with_italian_labels(): void
    {
        $this->actingAs(User::factory()->create());
        Dashboard::create(['name' => 'ANOMALIE', 'order' => 1, 'is_active' => true]);

        Livewire::test(ManageDashboards::class)
            ->assertSee('ANOMALIE')
            ->assertSee('Categoria')
            ->assertSee('Widget')
            ->assertSee('Nuovo')
            ->assertDontSee('New dashboard');
    }
}
