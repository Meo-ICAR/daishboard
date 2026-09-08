---
paths:
  - app/Filament/Resources/DashboardWidgets/Pages/ViewDashboardWidget.php
---

# Dashboard Widgets Pages

## ViewDashboardWidget: actions & drill payload
All actions live in the TABLE header via `->headerActions($this->tableHeaderActions())` — there is NO getHeaderActions() override, so the Filament page header is just heading + subheading at full width (avoids the squeeze when many buttons + a long subheading share the header flex row). The `drill` URL param is base64url(JSON) of a LIST of {label, expr, value}: `label` = the result column name (shown in the subheading, value truncated to 60), `expr` = the SQL dimension expression (used to build the WHERE for convertSqlStringToDrillDown). decodeDrill() also accepts the legacy `{expr: value}` map form from old bookmarks.
