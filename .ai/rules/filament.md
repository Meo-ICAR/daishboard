---
paths:
  - 'app/Filament/**'
---

# Filament

## Tabelle drill-down page + DashboardWidget type filter
DashboardTablesOverview (nav "Tabelle"): a plain LIST drill-down, not a preview grid — categories -> dashboards -> table master widgets -> table children. It does NOT run any widget SQL; clicking a table row opens ViewDashboardWidget (which shows rows, and for GROUP BY queries the numeric-cell -> detail drill-down). Levels via #[Url] category(0=uncategorized)/dashboardId/master; only widgets with COALESCE(LOWER(type),'')='table'; categories/dashboards filtered by whereHas to those containing a table. DashboardWidgetsTable has a SelectFilter 'is_table' with placeholder 'Tutti' and options table / not_table applying whereRaw("COALESCE(LOWER(type),'') = / <> 'table'").
