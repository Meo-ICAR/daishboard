---
paths:
  - 'app/Filament/Resources/DashboardWidgets/**'
---

# Dashboard Widgets

## Widget inherits cohort filters from its Project
dashboard_widgets.project_id and dashboard_widget_shares.project_id (both nullable FK -> projects). When a widget has a project, InteractsWithWidgetDataset::cohortFilters() prepends $widget->project->cohortFilters() (merged date + value entries) to the manual page date filters, all AND-ed, and passes them to WidgetDatasetRunner::run(). SharedWidgetController does the same with $share->project. The widget edit form's project_id Select is scoped by modifyQueryUsing to projects whose database is NULL or equals the selected dashboard's database (dashboard_id is ->live()). Creating a public share copies the widget's project_id onto the share.

## Generated drill-down for widgets without child widgets
ViewDashboardWidget: a numeric cell on an aggregated widget (single SELECT + GROUP BY) with NO child DashboardWidget links back to itself with a `drill` param = base64url(JSON) of {sql-expression => row-value} for the row's non-numeric dimensions. On mount the param is decoded into `$drillFilters`; InteractsWithWidgetDataset::runQuery() runs `datasetQuery()`, which ViewDashboardWidget overrides to return `DashboardWidget::convertSqlStringToDrillDown($widgetQuery, $drillFilters)` + `LIMIT 2000` when drilling. Dimension alias→expression mapping comes from `DashboardWidget::selectDimensionExpressions()` (only `expr AS alias` and plain identifiers are mapped; computed columns without AS are skipped). `convertSqlStringToDrillDown` strips GROUP BY/HAVING/ORDER BY, unpacks aggregates to their inner field, and falls back to `SELECT *` when the only aggregate was COUNT(*)/COUNT(1). A "Torna all'aggregato" header action clears the drill.

## Widget list table + view: recent additions
DashboardWidgetsTable: every column is ->sortable() (relationship columns dashboard.title / masterWidget.title use plain ->sortable() — a `sortable(['fk'])` array wrongly qualifies the FK to the related table and breaks). Filters: dashboard_id (default = user's remembered dashboard), project_id (relationship), menu_category (custom ->query whereHas dashboard.menu_category_id), is_table (options 'table'/'not_table' — keys must match the match() arms). Record action 'duplicate': replicate() + saveQuietly() (keeps company_id/project_id/dashboard_id/master_widget_id, title gets ' (copia)'); a user_id Select is ->visible() only when $record->user_id !== null, default = same user_id, ->nullable(); the action sets $copy->user_id only when array_key_exists('user_id', $data). The Select options come from scoped User::query() so a normal user can only reassign to themselves.
ViewDashboardWidget: the 'chart' table-header action is hidden when strtolower($widgetType) === 'table'. Every numeric column gets ->summarize(Summarizer::make()->using(fn () => $this->columnSum($name))) — a footer row DOES render for the ->records() custom-data table; columnSum() sums $this->queryRows and formats IT-locale.
