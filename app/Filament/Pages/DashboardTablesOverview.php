<?php

namespace App\Filament\Pages;

use App\Filament\Resources\DashboardWidgets\DashboardWidgetResource;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\MenuCategory;
use App\Support\CompanyScope;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

/**
 * Elenco delle tabelle con drill-down:
 * categorie di menu → dashboard → widget master (type = table) → widget figli.
 * Ogni voce è un link; sulle tabelle si apre la vista con le righe (dove, se la
 * query ha un GROUP BY, cliccando una colonna numerica si vede il dettaglio).
 */
class DashboardTablesOverview extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $navigationLabel = 'Tabelle';

    protected static ?string $title = 'Tabelle';

    protected string $view = 'filament.pages.dashboard-tables-overview';

    /** Categoria selezionata; 0 = dashboard senza categoria. */
    #[Url]
    public ?int $category = null;

    #[Url]
    public ?int $dashboardId = null;

    /** Widget master selezionato: se valorizzato mostra i suoi figli. */
    #[Url]
    public ?int $master = null;

    /** categories | dashboards | masters | children */
    public string $level = 'categories';

    public ?string $heading = null;

    /** @var array<int, array<string, mixed>> */
    public array $items = [];

    /**
     * Percorso di navigazione (breadcrumb del drill-down).
     *
     * @var array<int, array{label: string, url: ?string}>
     */
    public array $trail = [];

    public function mount(): void
    {
        $this->rebuild();
    }

    public function updatedCategory(): void
    {
        $this->dashboardId = null;
        $this->master = null;
        $this->rebuild();
    }

    public function updatedDashboardId(): void
    {
        $this->master = null;
        $this->rebuild();
    }

    public function updatedMaster(): void
    {
        $this->rebuild();
    }

    protected function rebuild(): void
    {
        $master = $this->master !== null
            ? DashboardWidget::query()->whereKey($this->master)->first()
            : null;

        if ($master !== null) {
            $this->dashboardId = (int) $master->dashboard_id;
        }

        $dashboard = $this->dashboardId !== null
            ? Dashboard::query()->whereKey($this->dashboardId)->first()
            : null;

        if ($dashboard !== null) {
            $this->category = (int) ($dashboard->menu_category_id ?? 0);
        }

        if ($master !== null && $dashboard !== null) {
            $this->level = 'children';
            $this->heading = 'Tabelle collegate a: '.($master->title ?? ('Widget #'.$master->getKey()));
            $this->items = $this->childItems($master);
        } elseif ($dashboard !== null) {
            $this->level = 'masters';
            $this->heading = 'Tabelle di: '.($dashboard->title ?? ('Dashboard #'.$dashboard->getKey()));
            $this->items = $this->masterItems($dashboard);
        } elseif ($this->category !== null) {
            $this->level = 'dashboards';
            $this->heading = 'Dashboard';
            $this->items = $this->dashboardItems($this->category);
        } else {
            $this->level = 'categories';
            $this->heading = 'Categorie';
            $this->items = $this->categoryItems();
        }

        $this->trail = $this->buildTrail($dashboard, $master);
    }

    /**
     * Solo i widget di tipo tabella (type = 'table', case-insensitive).
     *
     * @param  Builder<DashboardWidget>  $query
     * @return Builder<DashboardWidget>
     */
    protected function onlyTables(Builder $query): Builder
    {
        return $query->whereRaw("COALESCE(LOWER(type), '') = 'table'");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function categoryItems(): array
    {
        $items = CompanyScope::byDatabase(MenuCategory::query())
            ->where('is_active', true)
            ->whereHas('dashboards.widgets', fn (Builder $q) => $this->onlyTables($q))
            ->orderBy('order')
            ->orderBy('name')
            ->get()
            ->map(fn (MenuCategory $category): array => [
                'type' => 'drill',
                'title' => $category->name,
                'subtitle' => $category->description,
                'icon' => $category->icon ?: 'heroicon-o-folder',
                'meta' => $this->tableCountForCategory($category->getKey()).' tabelle',
                'url' => static::getUrl(['category' => $category->getKey()]),
            ])
            ->all();

        $uncategorized = $this->tableCountForCategory(0);

        if ($uncategorized > 0) {
            $items[] = [
                'type' => 'drill',
                'title' => 'Senza categoria',
                'subtitle' => null,
                'icon' => 'heroicon-o-folder',
                'meta' => $uncategorized.' tabelle',
                'url' => static::getUrl(['category' => 0]),
            ];
        }

        return $items;
    }

    protected function tableCountForCategory(int $category): int
    {
        return $this->onlyTables(
            DashboardWidget::query()
                ->whereNull('master_widget_id')
                ->whereHas('dashboard', fn (Builder $q) => $category === 0
                    ? $q->whereNull('menu_category_id')
                    : $q->where('menu_category_id', $category)),
        )->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function dashboardItems(int $category): array
    {
        return Dashboard::query()
            ->when(
                $category === 0,
                fn (Builder $q) => $q->whereNull('menu_category_id'),
                fn (Builder $q) => $q->where('menu_category_id', $category),
            )
            ->whereHas('widgets', fn (Builder $q) => $this->onlyTables($q))
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->map(fn (Dashboard $dashboard): array => [
                'type' => 'drill',
                'title' => $dashboard->title,
                'subtitle' => $dashboard->description,
                'icon' => $dashboard->icon ?: 'heroicon-o-rectangle-stack',
                'meta' => $this->onlyTables(
                    DashboardWidget::query()
                        ->where('dashboard_id', $dashboard->getKey())
                        ->whereNull('master_widget_id'),
                )->count().' tabelle',
                'url' => static::getUrl(['category' => $this->category, 'dashboardId' => $dashboard->getKey()]),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function masterItems(Dashboard $dashboard): array
    {
        return $this->onlyTables(
            DashboardWidget::query()
                ->where('dashboard_id', $dashboard->getKey())
                ->whereNull('master_widget_id')
                ->where('is_active', true),
        )
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->map(fn (DashboardWidget $widget): array => $this->widgetItem($widget))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function childItems(DashboardWidget $master): array
    {
        $items = [$this->widgetItem($master, isCurrentMaster: true)];

        $children = $this->onlyTables($master->detailWidgets()->getQuery())
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        foreach ($children as $child) {
            $items[] = $this->widgetItem($child);
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    protected function widgetItem(DashboardWidget $widget, bool $isCurrentMaster = false): array
    {
        $childCount = DashboardWidget::query()->where('master_widget_id', $widget->getKey())->count();

        return [
            'type' => 'widget',
            'id' => $widget->getKey(),
            'title' => $widget->title ?? ('Widget #'.$widget->getKey()),
            'isCurrentMaster' => $isCurrentMaster,
            'childCount' => $childCount,
            'url' => DashboardWidgetResource::getUrl('view', ['record' => $widget->getKey()]),
            'drillUrl' => ($childCount > 0 && ! $isCurrentMaster)
                ? static::getUrl([
                    'category' => $this->category,
                    'dashboardId' => $this->dashboardId,
                    'master' => $widget->getKey(),
                ])
                : null,
        ];
    }

    /**
     * @return array<int, array{label: string, url: ?string}>
     */
    protected function buildTrail(?Dashboard $dashboard, ?DashboardWidget $master): array
    {
        $trail = [['label' => 'Tabelle', 'url' => static::getUrl()]];

        if ($this->category !== null) {
            $trail[] = [
                'label' => $this->category === 0
                    ? 'Senza categoria'
                    : (MenuCategory::whereKey($this->category)->value('name') ?? 'Categoria'),
                'url' => static::getUrl(['category' => $this->category]),
            ];
        }

        if ($dashboard !== null) {
            $trail[] = [
                'label' => $dashboard->title ?? ('Dashboard #'.$dashboard->getKey()),
                'url' => static::getUrl(['category' => $this->category, 'dashboardId' => $dashboard->getKey()]),
            ];
        }

        if ($master !== null) {
            $trail[] = [
                'label' => $master->title ?? ('Widget #'.$master->getKey()),
                'url' => null,
            ];
        }

        return $trail;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetTables')
                ->label('Tutte le categorie')
                ->icon(Heroicon::OutlinedSquares2x2)
                ->color('gray')
                ->visible(fn (): bool => $this->level !== 'categories')
                ->url(fn (): string => static::getUrl()),

            Action::make('allTables')
                ->label('Tutte le tabelle')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->visible(fn (): bool => $this->level === 'children')
                ->url(fn (): string => static::getUrl([
                    'category' => $this->category,
                    'dashboardId' => $this->dashboardId,
                ])),
        ];
    }
}
