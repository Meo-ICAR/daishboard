<?php

namespace Tests\Feature;

use App\Filament\Resources\DashboardWidgets\Pages\ViewDashboardWidget;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\DashboardWidgetShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WidgetShareModalTest extends TestCase
{
    use RefreshDatabase;

    private DashboardWidget $widget;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->widget = DashboardWidget::create([
            'dashboard_id' => Dashboard::create(['title' => 'D', 'order' => 0, 'is_active' => true])->id,
            'title' => 'Pazienti per etnia',
            'type' => 'table',
            'query' => 'SELECT etnia_id, COUNT(*) AS n FROM patients WHERE active = 1 GROUP BY etnia_id',
            'order' => 0,
            'is_active' => true,
        ]);
    }

    private function share(array $overrides = []): DashboardWidgetShare
    {
        return DashboardWidgetShare::create(array_merge([
            'token' => DashboardWidgetShare::generateToken(),
            'dashboard_widget_id' => $this->widget->id,
            'created_by' => auth()->id(),
            'title' => 'Link di prova',
            'parameters' => ['dateFilters' => []],
            'include_children' => true,
        ], $overrides));
    }

    public function test_share_actions_are_hidden_when_there_are_no_shares(): void
    {
        Livewire::test(ViewDashboardWidget::class, ['record' => $this->widget->id])
            ->assertTableActionHidden('sharesList')
            ->assertTableActionHidden('revokeShares');
    }

    public function test_shares_list_action_is_visible_and_mountable_when_shares_exist(): void
    {
        $this->share();

        Livewire::test(ViewDashboardWidget::class, ['record' => $this->widget->id])
            ->assertTableActionVisible('sharesList')
            ->mountTableAction('sharesList')
            ->assertTableActionMounted('sharesList')
            ->assertHasNoTableActionErrors();
    }

    public function test_share_links_payload_feeds_the_repeatable_entry(): void
    {
        $share = $this->share(['expires_at' => now()->addDays(7)]);

        $links = Livewire::test(ViewDashboardWidget::class, ['record' => $this->widget->id])
            ->instance()
            ->shareLinks();

        $this->assertCount(1, $links);
        $this->assertSame('Link di prova', $links[0]['title']);
        $this->assertSame($share->publicUrl(), $links[0]['url']);
        $this->assertStringContainsString('Creato', $links[0]['meta']);
        $this->assertStringContainsString('figli inclusi', $links[0]['meta']);
        $this->assertStringContainsString('scade', $links[0]['meta']);
    }

    public function test_the_share_action_creates_a_link_and_a_notification_with_an_open_action(): void
    {
        Livewire::test(ViewDashboardWidget::class, ['record' => $this->widget->id])
            ->callTableAction('share', data: [
                'title' => 'Nuovo link',
                'expiry' => '7',
                'include_children' => true,
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Link pubblico creato');

        $this->assertSame(1, DashboardWidgetShare::query()
            ->where('dashboard_widget_id', $this->widget->id)
            ->where('title', 'Nuovo link')
            ->count());
    }

    public function test_revoke_all_deletes_every_share_of_the_widget(): void
    {
        $this->share();
        $this->share(['title' => 'Secondo link']);

        Livewire::test(ViewDashboardWidget::class, ['record' => $this->widget->id])
            ->callTableAction('revokeShares')
            ->assertTableActionHidden('sharesList');

        $this->assertSame(0, DashboardWidgetShare::query()->where('dashboard_widget_id', $this->widget->id)->count());
    }
}
