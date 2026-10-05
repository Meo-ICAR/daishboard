<?php

namespace Tests\Feature;

use App\Models\ChatHistory;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatHistoryOwnedScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_sees_chat_histories_of_same_company_users(): void
    {
        $company = Company::create(['name' => 'ACME']);
        $otherCompany = Company::create(['name' => 'Other']);

        $admin = User::factory()->create(['is_admin' => true, 'company_id' => $company->id]);
        $colleague = User::factory()->create(['is_admin' => false, 'company_id' => $company->id]);
        $outsider = User::factory()->create(['is_admin' => false, 'company_id' => $otherCompany->id]);
        $ownerless = User::factory()->create(['is_admin' => false, 'company_id' => null]);

        $visibleColleague = ChatHistory::factory()->create(['user_id' => $colleague->id]);
        $visibleOwnerless = ChatHistory::factory()->create(['user_id' => $ownerless->id]);
        $hiddenOutsider = ChatHistory::factory()->create(['user_id' => $outsider->id]);

        $this->actingAs($admin);

        $visibleIds = ChatHistory::query()->pluck('id')->all();

        $this->assertContains($visibleColleague->id, $visibleIds);
        $this->assertContains($visibleOwnerless->id, $visibleIds);
        $this->assertNotContains($hiddenOutsider->id, $visibleIds);
    }

    public function test_normal_user_only_sees_own_chat_histories(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->create(['is_admin' => false]);

        $own = ChatHistory::factory()->create(['user_id' => $user->id]);
        ChatHistory::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user);

        $this->assertSame([$own->id], ChatHistory::query()->pluck('id')->all());
    }
}
