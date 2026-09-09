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
 * Elenco delle tabelle: scelta la categoria di menu, ogni dashboard è una
 * sezione richiudibile che elenca le sue tabelle (widget master `type = table`,
 * con le eventuali tabelle di dettaglio annidate). Cliccando una tabella si apre
 * la vista con le righe.
 */
class DashboardTablesOverview extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $navigationLabel = 'Tabelle';

    protected static ?string $title = 'Tabelle';

    protected string $view = 'filament.pages.dashboard-tables-overview';

    /** Categoria selezionata; 0 = dashboard senza categoria; null = scegli. */
    #[Url]
    public ?int $category = null;

    /** Solo per riprendere l'ultima dashboard: ne deriva la categoria e la sezione da aprire. */
    #[Url]
    public ?int $dashboardId = null;

    /** categories | sections */
    public string $level = 'categories';

    public ?string $heading = null;

    /**
     * Categorie fra cui scegliere (livello `categories`).
     *
     * @var array<int, array{id: int, name: string, count: int}>
     */
    public array $categories = [];

    /**
     * Sezioni dashboard con le loro tabelle (livello `sections`).
     *
     * @var array<int, array<string, mixed>>
     */
    public array $sections = [];

    /**
     * Percorso di navigazione (breadcrumb).
     *
     * @var array<int, array{label: string, url: ?string}>
     */
    public array $trail = [];

    public function mount(): void
    {
        // Visita "pulita": riparte dall'ultima dashboard selezionata dall'utente.
        if ($this->category === null && $this->dashboardId === null) {
            $this->dashboardId = auth()->user()?->dashboard_id;
        }

        $this->rebuild();
    }

    public function updatedCategory(): void
    {
        $this->dashboardId = null;
        $this->rebuild();
    }

    protected function rebuild(): void
    {
        // Da un dashboardId ricordato/passato si risale alla sua categoria.
        $expandDashboardId = $this->dashboardId;

        if ($this->category === null && $this->dashboardId !== null) {
            $menuCategoryId = Dashboard::query()->whereKey($this->dashboardId)->value('menu_category_id');
            $this->category = $menuCategoryId !== null ? (int) $menuCategoryId : 0;
        }

        $this->categories = $this->categoriesWithTables();

        if ($this->category === null && count($this->categories) > 1) {
            $this->level = 'categories';
            $this->heading = null;
            $this->sections = [];
        } else {
            $this->category ??= $this->categories[0]['id'] ?? 0;
            $this->level = 'sections';
            $this->heading = $this->categoryLabel($this->category);
            $this->sections = $this->dashboardSections($this->category, $expandDashboardId);
        }

        $this->trail = $this->buildTrail();
    }

    /**
     * @param  Builder<DashboardWidget>  $query
     * @return Builder<DashboardWidget>
     */
    protected function onlyTables(Builder $query): Builder
    {
        return $query->whereRaw("COALESCE(LOWER(type), '') = 'table'");
    }

    /**
     * Categorie che contengono almeno una tabella, più il gruppo "Senza
     * categoria" quando esistono dashboard senza categoria con tabelle.
     *
     * @return array<int, array{id: int, name: string, count: int}>
     */
    protected function categoriesWithTables(): array
    {
        $categories = CompanyScope::byDatabase(MenuCategory::query())
            ->where('is_active', true)
            ->whereHas('dashboards.widgets', fn (Builder $q) => $this->onlyTables($q))
            ->orderBy('order')
            ->orderBy('name')
            ->get()
            ->map(fn (MenuCategory $category): array => [
                'id' => (int) $category->getKey(),
                'name' => $category->name,
                'count' => $this->tableCountForCategory((int) $category->getKey()),
            ])
            ->all();

        $uncategorized = $this->tableCountForCategory(0);

        if ($uncategorized > 0) {
            $categories[] = ['id' => 0, 'name' => 'Senza categoria', 'count' => $uncategorized];
        }

        return $categories;
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

    protected function categoryLabel(int $category): string
    {
        return $category === 0
            ? 'Senza categoria'
            : (MenuCategory::whereKey($category)->value('name') ?? 'Categoria');
    }

    /**
     * Dashboard della categoria (con almeno una tabella) come sezioni; la
     * sezione della dashboard indicata da $expandId (o la prima) è espansa.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function dashboardSections(int $category, ?int $expandId): array
    {
        $sections = Dashboard::query()
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
                'id' => (int) $dashboard->getKey(),
                'title' => $dashboard->title ?? ('Dashboard #'.$dashboard->getKey()),
                'tables' => $this->tableTree($dashboard),
                'expanded' => $expandId !== null && (int) $dashboard->getKey() === $expandId,
            ])
            ->values()
            ->all();

        // Se nessuna sezione risulta espansa, apri la prima.
        if ($sections !== [] && ! collect($sections)->contains('expanded', true)) {
            $sections[0]['expanded'] = true;
        }

        return $sections;
    }

    /**
     * Widget master di tipo tabella della dashboard, con le tabelle di dettaglio
     * (figli) annidate.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableTree(Dashboard $dashboard): array
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
            ->map(fn (DashboardWidget $master): array => [
                'id' => (int) $master->getKey(),
                'title' => $master->title ?? ('Widget #'.$master->getKey()),
                'url' => DashboardWidgetResource::getUrl('view', ['record' => $master->getKey()]),
                'children' => $this->onlyTables($master->detailWidgets()->getQuery())
                    ->orderBy('order')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (DashboardWidget $child): array => [
                        'id' => (int) $child->getKey(),
                        'title' => $child->title ?? ('Widget #'.$child->getKey()),
                        'url' => DashboardWidgetResource::getUrl('view', ['record' => $child->getKey()]),
                    ])
                    ->all(),
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, url: ?string}>
     */
    protected function buildTrail(): array
    {
        $trail = [['label' => 'Tabelle', 'url' => static::getUrl()]];

        if ($this->level === 'sections' && $this->category !== null) {
            $trail[] = ['label' => $this->categoryLabel($this->category), 'url' => null];
        }

        return $trail;
    }

    /**
     * Breadcrumb nativo di Filament (sopra il titolo): l'ultima voce è la pagina
     * corrente e non è cliccabile.
     *
     * @return array<string, string>|array<int, string>
     */
    public function getBreadcrumbs(): array
    {
        if (count($this->trail) <= 1) {
            return [];
        }

        $crumbs = [];
        $lastIndex = count($this->trail) - 1;

        foreach ($this->trail as $index => $crumb) {
            if ($index === $lastIndex) {
                $crumbs[] = $crumb['label'];

                continue;
            }

            $crumbs[$crumb['url'] ?? '#'] = $crumb['label'];
        }

        return $crumbs;
    }

    public function getSubheading(): ?string
    {
        if ($this->level === 'categories') {
            return 'Scegli una categoria per vederne le dashboard e le tabelle.';
        }

        return count($this->sections).' dashboard · espandi una sezione per aprire le sue tabelle.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetCategories')
                ->label('Tutte le categorie')
                ->icon(Heroicon::OutlinedSquares2x2)
                ->color('gray')
                ->visible(fn (): bool => $this->level === 'sections' && count($this->categories) > 1)
                ->url(fn (): string => static::getUrl()),
        ];
    }
}
