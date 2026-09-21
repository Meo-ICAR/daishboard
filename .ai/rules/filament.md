---
paths:
  - 'app/Filament/**'
---

# Filament

## Tabelle drill-down page + DashboardWidget type filter
DashboardTablesOverview (nav "Tabelle"): a plain LIST drill-down, not a preview grid — categories -> dashboards -> table master widgets -> table children. It does NOT run any widget SQL; clicking a table row opens ViewDashboardWidget (which shows rows, and for GROUP BY queries the numeric-cell -> detail drill-down). Levels via #[Url] category(0=uncategorized)/dashboardId/master; only widgets with COALESCE(LOWER(type),'')='table'; categories/dashboards filtered by whereHas to those containing a table. DashboardWidgetsTable has a SelectFilter 'is_table' with placeholder 'Tutti' and options table / not_table applying whereRaw("COALESCE(LOWER(type),'') = / <> 'table'").

## Trappola: due classi "ColumnsRelationManager" condividono lo stesso file di traduzioni
`App\Filament\Resources\SchemaLegends\RelationManagers\ColumnsRelationManager` e `App\Filament\Resources\LookupTables\RelationManagers\ColumnsRelationManager` sono due classi diverse con lo stesso basename, e la struttura `panel-based` di `lang/it/filament/admin/` le fa puntare **allo stesso file** `columns_relation_manager.php` (chiave = basename della classe, non FQCN). Se aggiungi/rinomini una chiave `__('filament/admin/columns_relation_manager.xxx')` in una delle due classi, verifica che la chiave esista in quel file anche per l'ALTRA classe (es. `legend.table_name`, usata solo da quella di LookupTables, era assente e mostrava la stringa raw della chiave in UI). Stessa cautela per qualunque altra coppia di classi Filament omonime in namespace diversi.
