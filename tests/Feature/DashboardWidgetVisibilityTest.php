<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWidgetVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private User $userA1;

    private User $userA2;

    /** @var array<string, int> widget key => id */
    private array $widgets = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create(['name' => 'A']);
        $this->companyB = Company::create(['name' => 'B']);

        $this->userA1 = User::factory()->create(['company_id' => $this->companyA->id, 'is_admin' => false]);
        $this->userA2 = User::factory()->create(['company_id' => $this->companyA->id, 'is_admin' => false]);
        $userB = User::factory()->create(['company_id' => $this->companyB->id, 'is_admin' => false]);

        $dashboardId = Dashboard::withoutEvents(fn () => Dashboard::create([
            'title' => 'D', 'order' => 0, 'is_active' => true,
        ]))->id;

        $make = fn (?int $companyId, ?int $userId): int => DashboardWidget::withoutEvents(
            fn () => DashboardWidget::query()->withoutGlobalScopes()->create([
                'dashboard_id' => $dashboardId,
                'company_id' => $companyId,
                'user_id' => $userId,
                'title' => 'W', 'type' => 'Table', 'query' => 'SELECT 1', 'order' => 0, 'is_active' => true,
            ]),
        )->id;

        $this->widgets = [
            'global' => $make(null, null),
            'A_u1' => $make($this->companyA->id, $this->userA1->id),
            'A_u2' => $make($this->companyA->id, $this->userA2->id),
            'A_nouser' => $make($this->companyA->id, null),
            'B_u' => $make($this->companyB->id, $userB->id),
        ];
    }

    /**
     * @return list<string>
     */
    private function visibleKeysFor(User $user): array
    {
        $this->actingAs($user);

        $ids = DashboardWidget::query()->pluck('id')->all();

        return collect($this->widgets)
            ->filter(fn (int $id): bool => in_array($id, $ids, true))
            ->keys()
            ->all();
    }

    public function test_super_admin_sees_every_widget(): void
    {
        $superAdmin = User::factory()->create(['company_id' => null, 'is_admin' => true]);

        $this->assertEqualsCanonicalizing(
            ['global', 'A_u1', 'A_u2', 'A_nouser', 'B_u'],
            $this->visibleKeysFor($superAdmin),
        );
    }

    public function test_company_admin_sees_only_its_company_and_global_widgets(): void
    {
        $adminA = User::factory()->create(['company_id' => $this->companyA->id, 'is_admin' => true]);

        $this->assertEqualsCanonicalizing(
            ['global', 'A_u1', 'A_u2', 'A_nouser'],
            $this->visibleKeysFor($adminA),
        );
    }

    public function test_normal_user_sees_only_its_own_company_and_owner_scoped_widgets(): void
    {
        $this->assertEqualsCanonicalizing(
            ['global', 'A_u1', 'A_nouser'],
            $this->visibleKeysFor($this->userA1),
        );
    }

    public function test_a_second_normal_user_of_the_same_company_sees_only_its_own(): void
    {
        $this->assertEqualsCanonicalizing(
            ['global', 'A_u2', 'A_nouser'],
            $this->visibleKeysFor($this->userA2),
        );
    }
}
