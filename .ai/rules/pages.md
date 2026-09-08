---
paths:
  - app/Filament/Pages/DashboardTablesOverview.php
---

# Pages

## Tabelle: drill-down overview page
DashboardTablesOverview (nav "Tabelle", auto-discovered) mirrors DashboardChartsOverview but for table widgets. 4 drill levels via #[Url] params: category (0 = uncategorized) -> dashboardId -> master. rebuild() resolves the chain upward from the deepest set param and derives dashboardId/category from the master/dashboard. `level` is one of categories|dashboards|masters|children. Only widgets with COALESCE(LOWER(type),'') = 'table' are shown (exact inverse of the charts overview filter); categories/dashboards are filtered by whereHas to those actually containing a table. Widget scope = CompanyScope::byOwner + model owned global scope; categories = CompanyScope::byDatabase. Each widget card shows a 10-row preview via WidgetDatasetRunner (with the current Project cohort filters) and links to ViewDashboardWidget.
