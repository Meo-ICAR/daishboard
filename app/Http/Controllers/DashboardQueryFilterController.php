<?php

namespace App\Http\Controllers;

use App\Models\DashboardQuery;
use App\Services\DateFieldSemanticsMap;
use App\Services\ResearchDateRangeResolver;
use App\Services\TableSchemaInspector;

class DashboardQueryFilterController extends Controller
{
    public function __construct(
        protected TableSchemaInspector $inspector,
        protected DateFieldSemanticsMap $semanticsMap,
        protected ResearchDateRangeResolver $rangeResolver,
    ) {}

    public function index(DashboardQuery $dashboardQuery)
    {
        $columns = collect($dashboardQuery->tables)
            ->flatMap(fn ($table) => collect($this->inspector->getDateFilterableColumns($table))
                ->map(fn ($col) => array_merge($col, ['table' => $table])));

        $result = $columns->map(function ($col) {
            $category = $this->semanticsMap->categoryFor($col['table'], $col['column']);

            return array_merge($col, [
                'category' => $category?->value,
                'presets' => $category ? $this->rangeResolver->presetsFor($category) : [],
            ]);
        });

        return response()->json($result->values());
    }
}
