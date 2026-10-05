<?php

namespace App\Http\Controllers;

use App\Models\DashboardWidget;
use App\Models\DashboardWidgetShare;
use App\Services\WidgetDatasetRunner;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SharedWidgetController extends Controller
{
    public function __construct(protected WidgetDatasetRunner $runner) {}

    /**
     * Tabella principale condivisa dal link pubblico.
     */
    public function show(Request $request, string $token): View
    {
        $share = $this->resolveShare($token);

        $this->registerView($share);

        return $this->renderDataset(
            share: $share,
            widget: $share->dashboardWidget,
            masterField: null,
            isChild: false,
        );
    }

    /**
     * Tabella figlio (drill-down) raggiungibile dallo stesso link pubblico.
     */
    public function child(Request $request, string $token, int $child): View
    {
        $share = $this->resolveShare($token);

        abort_unless($share->include_children, Response::HTTP_FORBIDDEN, 'Le tabelle figlio non sono abilitate per questo link.');

        $childWidget = DashboardWidget::query()->findOrFail($child);

        abort_unless(
            $this->isWithinSharedTree($childWidget, (int) $share->dashboard_widget_id),
            Response::HTTP_NOT_FOUND,
        );

        $masterField = $request->query('MasterFilterField');
        $masterField = is_string($masterField) && $masterField !== '' ? $masterField : null;

        return $this->renderDataset(
            share: $share,
            widget: $childWidget,
            masterField: $masterField,
            isChild: true,
        );
    }

    protected function resolveShare(string $token): DashboardWidgetShare
    {
        $share = DashboardWidgetShare::query()
            ->with(['dashboardWidget', 'project'])
            ->where('token', $token)
            ->first();

        abort_if($share === null, Response::HTTP_NOT_FOUND);
        abort_if($share->isExpired(), Response::HTTP_GONE, 'Questo link è scaduto.');
        abort_if($share->dashboardWidget === null, Response::HTTP_NOT_FOUND);

        return $share;
    }

    protected function registerView(DashboardWidgetShare $share): void
    {
        $share->forceFill([
            'views' => $share->views + 1,
            'last_viewed_at' => now(),
        ])->saveQuietly();
    }

    /**
     * Un widget è visualizzabile dal link se coincide con la radice condivisa
     * o è un suo discendente lungo la catena master_widget_id.
     */
    protected function isWithinSharedTree(DashboardWidget $widget, int $rootId, int $maxDepth = 10): bool
    {
        $current = $widget;

        for ($depth = 0; $depth <= $maxDepth; $depth++) {
            if ((int) $current->getKey() === $rootId) {
                return true;
            }

            if ($current->master_widget_id === null) {
                return false;
            }

            $current = DashboardWidget::query()->find($current->master_widget_id);

            if ($current === null) {
                return false;
            }
        }

        return false;
    }

    protected function renderDataset(
        DashboardWidgetShare $share,
        DashboardWidget $widget,
        ?string $masterField,
        bool $isChild,
    ): View {
        $filters = [
            ...$share->project?->cohortFilters() ?? [],
            ...$share->dateFilters(),
        ];

        $result = $this->runner->run($widget->query, $filters);
        $filterDescription = $this->runner->describeFilters($widget->query, $filters);

        $children = $result['error'] === null && $share->include_children
            ? $widget->detailWidgets()->orderBy('order')->orderBy('id')->get(['id', 'title', 'master_filter_column'])
            : collect();

        return view('shared.widget', [
            'share' => $share,
            'widget' => $widget,
            'isChild' => $isChild,
            'pageTitle' => $isChild
                ? ($widget->title ?? ('Widget #'.$widget->getKey()))
                : ($share->title ?: ($widget->title ?? ('Widget #'.$widget->getKey()))),
            'masterField' => $masterField,
            'columns' => $result['columns'],
            'numericColumns' => $result['numericColumns'],
            'rows' => $result['rows'],
            'error' => $result['error'],
            'filterDescription' => $filterDescription,
            'firstColumn' => $result['columns'][0] ?? null,
            'drilldownFor' => function (string $column) use ($children) {
                foreach ($children as $child) {
                    if ($child->master_filter_column === null || $child->master_filter_column === $column) {
                        return $child;
                    }
                }

                return null;
            },
            'childUrl' => fn (int $childId, array $query = []): string => route('shared.widget.child', array_merge(
                ['token' => $share->token, 'child' => $childId],
                array_filter($query, static fn ($value): bool => $value !== null && $value !== ''),
            )),
            'rootUrl' => route('shared.widget', ['token' => $share->token]),
        ]);
    }
}
