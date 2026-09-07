<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWidgetOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function dashboard(User $user): Dashboard
    {
        return Dashboard::create([
            'user_id' => $user->id,
            'title' => 'Dashboard test',
            'order' => 0,
            'is_active' => true,
        ]);
    }

    public function test_new_widget_inherits_user_and_company_from_the_authenticated_user(): void
    {
        $company = Company::create(['name' => 'ACME']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $this->actingAs($user);

        $widget = DashboardWidget::create([
            'dashboard_id' => $this->dashboard($user)->id,
            'title' => 'Widget',
            'type' => 'Table',
            'query' => 'SELECT 1',
            'order' => 0,
            'is_active' => true,
        ]);

        $this->assertSame($user->id, $widget->user_id);
        $this->assertSame($company->id, $widget->company_id);
    }

    public function test_explicit_values_are_not_overwritten(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $this->actingAs($actor);

        $widget = DashboardWidget::create([
            'dashboard_id' => $this->dashboard($actor)->id,
            'user_id' => $owner->id,
            'company_id' => null,
            'title' => 'Widget',
            'type' => 'Table',
            'query' => 'SELECT 1',
            'order' => 0,
            'is_active' => true,
        ]);

        $this->assertSame($owner->id, $widget->user_id);
    }
}
