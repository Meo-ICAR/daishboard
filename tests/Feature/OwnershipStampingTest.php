<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OwnershipStampingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string<Model>}>
     */
    public static function stampedModels(): array
    {
        return [
            'DashboardWidget' => [DashboardWidget::class],
            'Dashboard' => [Dashboard::class],
            'Project' => [Project::class],
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function create(string $modelClass, array $attributes = []): Model
    {
        $base = match ($modelClass) {
            DashboardWidget::class => [
                'dashboard_id' => Dashboard::create(['title' => 'D', 'order' => 0, 'is_active' => true])->id,
                'title' => 'W', 'type' => 'Table', 'query' => 'SELECT 1', 'order' => 0, 'is_active' => true,
            ],
            Dashboard::class => ['title' => 'D', 'order' => 0, 'is_active' => true],
            Project::class => ['name' => 'Studio', 'date_filters' => [], 'is_current' => true],
        };

        return $modelClass::create([...$base, ...$attributes]);
    }

    #[DataProvider('stampedModels')]
    public function test_normal_user_gets_company_and_user(string $modelClass): void
    {
        $company = Company::create(['name' => 'ACME']);
        $user = User::factory()->create(['company_id' => $company->id, 'is_admin' => false]);
        $this->actingAs($user);

        $record = $this->create($modelClass);

        $this->assertSame($user->id, $record->user_id);
        $this->assertSame($company->id, $record->company_id);
    }

    #[DataProvider('stampedModels')]
    public function test_company_admin_gets_company_but_no_user(string $modelClass): void
    {
        $company = Company::create(['name' => 'ACME']);
        $admin = User::factory()->create(['company_id' => $company->id, 'is_admin' => true]);
        $this->actingAs($admin);

        $record = $this->create($modelClass);

        $this->assertNull($record->user_id);
        $this->assertSame($company->id, $record->company_id);
    }

    #[DataProvider('stampedModels')]
    public function test_super_admin_gets_no_owner(string $modelClass): void
    {
        $superAdmin = User::factory()->create(['company_id' => null, 'is_admin' => true]);
        $this->actingAs($superAdmin);

        $record = $this->create($modelClass);

        $this->assertNull($record->user_id);
        $this->assertNull($record->company_id);
    }

    #[DataProvider('stampedModels')]
    public function test_explicit_non_null_values_are_not_overwritten(string $modelClass): void
    {
        $actorCompany = Company::create(['name' => 'ACME']);
        $otherCompany = Company::create(['name' => 'Globex']);
        $owner = User::factory()->create(['is_admin' => false]);
        $actor = User::factory()->create(['company_id' => $actorCompany->id, 'is_admin' => false]);
        $this->actingAs($actor);

        $record = $this->create($modelClass, [
            'user_id' => $owner->id,
            'company_id' => $otherCompany->id,
        ]);

        $this->assertSame($owner->id, $record->user_id);
        $this->assertSame($otherCompany->id, $record->company_id);
    }
}
