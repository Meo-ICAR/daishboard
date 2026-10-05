---
paths:
  - app/Filament/Resources/DashboardWidgets/Pages/ViewDashboardWidget.php
---

# Dashboard Widgets Pages

## ViewDashboardWidget: actions & drill payload
All actions live in the TABLE header via `->headerActions($this->tableHeaderActions())` — there is NO getHeaderActions() override, so the Filament page header is just heading + subheading at full width (avoids the squeeze when many buttons + a long subheading share the header flex row). The `drill` URL param is base64url(JSON) of a LIST of {label, expr, value}: `label` = the result column name (shown in the subheading, value truncated to 60), `expr` = the SQL dimension expression (used to build the WHERE for convertSqlStringToDrillDown). decodeDrill() also accepts the legacy `{expr: value}` map form from old bookmarks.

## id → identifier column in table views
config/data_navigator.php profiles can carry `identifier_column` (hiv → 'pazientecode'). App\Support\DataNavigatorProfile::identifierColumn() returns it for the DB currently connected via `dbai` (honors a valid DATA_NAVIGATOR_PROFILE, else matches profile `databases`, else null). ViewDashboardWidget::datasetQuery() runs the widget SQL (and the generated drill-down SQL) through DashboardWidget::swapSelectIdentifier($sql, $col), which rewrites ONLY top-level SELECT items that are a bare `id` / `t.id` (with optional `AS id`) to `t.<col>` — never `id` inside functions/subqueries or other aliases. So on the hassisdadmin DB, table views show `pazientecode` instead of the technical `id`. resolveProfile() in DataNavigatorAgent now ignores a DATA_NAVIGATOR_PROFILE that isn't a known key instead of throwing.

## Table view: non-numeric columns are searchable
buildColumns() marks every column NOT in $numericColumns as ->searchable() (this shows the global search field). Since the table uses ->records() with custom array data, actual filtering is done in the records() closure: it injects ?string $search and keeps rows where rowMatchesSearch($row, $search) is true — a case-insensitive substring match against every non-numeric column's value. Numeric columns are never searched.
