---
paths:
  - app/Filament/Pages/DashboardTablesOverview.php
  - app/Filament/Pages/DashboardChartsOverview.php
---

# Pages

## Tabelle: drill-down overview page
DashboardTablesOverview (nav "Tabelle", auto-discovered) mirrors DashboardChartsOverview but for table widgets. 4 drill levels via #[Url] params: category (0 = uncategorized) -> dashboardId -> master. rebuild() resolves the chain upward from the deepest set param and derives dashboardId/category from the master/dashboard. `level` is one of categories|dashboards|masters|children. Only widgets with COALESCE(LOWER(type),'') = 'table' are shown (exact inverse of the charts overview filter); categories/dashboards are filtered by whereHas to those actually containing a table. Widget scope = CompanyScope::byOwner + model owned global scope; categories = CompanyScope::byDatabase. Each widget card shows a 10-row preview via WidgetDatasetRunner (with the current Project cohort filters) and links to ViewDashboardWidget.

## Charts overview: beaker icon for project widgets
rebuild() adds 'hasProject' (=> $widget->project_id !== null) and 'projectName' (=> $widget->project?->name) to each chart entry; topLevelMasters() and the master/children queries eager-load ->with('project'). The blade renders a heroicon-o-beaker next to the chart title when hasProject, with title/aria-label "Studio: {projectName}" (or "Filtri di studio applicati" if the name is missing).

## Tabelle: layout a schede + breadcrumb nativo
La blade rende $items come griglia di schede responsive (.dt-grid in <style> inline — le classi grid responsive arbitrarie non sono nella CSS precompilata di Filament), non più una lista. Le schede 'drill' (categoria/dashboard) sono link interi (wire:navigate) con tile-icona, sottotitolo (dt-clamp 2 righe) e pill meta 'N tabelle'; le schede 'widget' aprono la vista (view) e mostrano, se ci sono figli, un pulsante secondario '.dt-childlink' (relative z-10) che fa drill. $this->heading (proprietà pubblica → guida l'H1 di Filament) ora è solo il titolo di dashboard/master (livello categories → null → 'Tabelle'). getBreadcrumbs() costruisce il breadcrumb nativo da $this->trail (ultima voce = pagina corrente, non link; ritorna [] se il trail ha 1 sola voce). getSubheading() dà un suggerimento per livello.
