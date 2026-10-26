<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Dashboard;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyTenantSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $name): Company
    {
        return Company::create(['name' => $name]);
    }

    private function dashboard(string $name, ?int $companyId): Dashboard
    {
        return Dashboard::withoutGlobalScopes()->create([
            'name' => $name,
            'company_id' => $companyId,
            'is_active' => true,
        ]);
    }

    public function test_superadmin_can_choose_among_all_companies(): void
    {
        $a = $this->company('Alfa');
        $b = $this->company('Beta');
        $superadmin = User::factory()->create(['is_admin' => true, 'company_id' => null]);

        $tenants = $superadmin->getTenants(Filament::getPanel('admin'));

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $tenants->pluck('id')->all());
        $this->assertTrue($superadmin->canAccessTenant($b));
    }

    public function test_company_user_can_only_access_own_company(): void
    {
        $a = $this->company('Alfa');
        $b = $this->company('Beta');
        $user = User::factory()->create(['is_admin' => true, 'company_id' => $a->id]);

        $this->assertSame([$a->id], $user->getTenants(Filament::getPanel('admin'))->pluck('id')->all());
        $this->assertFalse($user->canAccessTenant($b));
    }

    public function test_superadmin_sees_only_selected_company_and_global_dashboards(): void
    {
        $a = $this->company('Alfa');
        $b = $this->company('Beta');
        $this->dashboard('di-alfa', $a->id);
        $this->dashboard('di-beta', $b->id);
        $this->dashboard('globale', null);

        $this->actingAs(User::factory()->create(['is_admin' => true, 'company_id' => null]));

        $this->assertEqualsCanonicalizing(['di-alfa', 'di-beta', 'globale'], Dashboard::pluck('name')->all());

        Filament::setTenant($a);

        $this->assertEqualsCanonicalizing(['di-alfa', 'globale'], Dashboard::pluck('name')->all());
    }

    public function test_records_created_by_superadmin_get_the_selected_company(): void
    {
        $a = $this->company('Alfa');
        $this->actingAs(User::factory()->create(['is_admin' => true, 'company_id' => null]));
        Filament::setTenant($a);

        $this->assertSame($a->id, $this->dashboard('nuova', null)->company_id);
    }
}
