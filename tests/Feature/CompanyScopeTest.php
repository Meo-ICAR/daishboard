<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DashboardWidget;
use App\Models\User;
use App\Support\CompanyScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyScopeTest extends TestCase
{
    use RefreshDatabase;

    private function scopedSql(): string
    {
        return CompanyScope::byOwner(DashboardWidget::query()->withoutGlobalScopes())->toRawSql();
    }

    public function test_super_admin_gets_no_owner_filter(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true, 'company_id' => null]));

        $sql = $this->scopedSql();

        $this->assertStringNotContainsString('user_id', $sql);
        $this->assertStringNotContainsString('company_id', $sql);
    }

    public function test_company_admin_is_filtered_by_company_only(): void
    {
        $company = Company::create(['name' => 'ACME']);
        $this->actingAs(User::factory()->create(['is_admin' => true, 'company_id' => $company->id]));

        $sql = $this->scopedSql();

        $this->assertStringContainsString('company_id', $sql);
        $this->assertStringNotContainsString('user_id', $sql);
    }

    public function test_normal_user_is_filtered_by_company_and_user(): void
    {
        $company = Company::create(['name' => 'ACME']);
        $this->actingAs(User::factory()->create(['is_admin' => false, 'company_id' => $company->id]));

        $sql = $this->scopedSql();

        $this->assertStringContainsString('company_id', $sql);
        $this->assertStringContainsString('user_id', $sql);
    }
}
