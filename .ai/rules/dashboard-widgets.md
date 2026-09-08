---
paths:
  - 'app/Filament/Resources/DashboardWidgets/**'
---

# Dashboard Widgets

## Widget inherits cohort filters from its Project
dashboard_widgets.project_id and dashboard_widget_shares.project_id (both nullable FK -> projects). When a widget has a project, InteractsWithWidgetDataset::cohortFilters() prepends $widget->project->cohortFilters() (merged date + value entries) to the manual page date filters, all AND-ed, and passes them to WidgetDatasetRunner::run(). SharedWidgetController does the same with $share->project. The widget edit form's project_id Select is scoped by modifyQueryUsing to projects whose database is NULL or equals the selected dashboard's database (dashboard_id is ->live()). Creating a public share copies the widget's project_id onto the share.

## Generated drill-down for widgets without child widgets
ViewDashboardWidget: a numeric cell on an aggregated widget (single SELECT + GROUP BY) with NO child DashboardWidget links back to itself with a `drill` param = base64url(JSON) of {sql-expression => row-value} for the row's non-numeric dimensions. On mount the param is decoded into `$drillFilters`; InteractsWithWidgetDataset::runQuery() runs `datasetQuery()`, which ViewDashboardWidget overrides to return `DashboardWidget::convertSqlStringToDrillDown($widgetQuery, $drillFilters)` + `LIMIT 2000` when drilling. Dimension alias→expression mapping comes from `DashboardWidget::selectDimensionExpressions()` (only `expr AS alias` and plain identifiers are mapped; computed columns without AS are skipped). `convertSqlStringToDrillDown` strips GROUP BY/HAVING/ORDER BY, unpacks aggregates to their inner field, and falls back to `SELECT *` when the only aggregate was COUNT(*)/COUNT(1). A "Torna all'aggregato" header action clears the drill.
