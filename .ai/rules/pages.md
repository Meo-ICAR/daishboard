---
paths:
  - app/Filament/Pages/DashboardTablesOverview.php
  - app/Filament/Pages/DashboardChartsOverview.php
---

# Pages

## Tabelle: elenco a sezioni per dashboard
DashboardTablesOverview (nav "Tabelle", auto-discovered). Due livelli soli (`level`): `categories` (scegli una categoria — mostrato solo se >1 categoria con tabelle) e `sections` (contenuto principale). A livello `sections` la blade rende, per la categoria scelta (`#[Url] category`, 0 = senza categoria), una `<x-filament::section collapsible>` per ogni dashboard che contiene tabelle; il corpo è un `<ul>` dei widget master `type = table` con le tabelle di dettaglio (figli, sempre `type = table`) annidate; ogni voce linka a ViewDashboardWidget. Solo widget con `COALESCE(LOWER(type),'') = 'table'`. La sezione della dashboard indicata da `#[Url] dashboardId` (o, su visita pulita, `auth()->user()->dashboard_id`) è espansa; se nessuna combacia, si espande la prima. Questa pagina NON scrive più `users.dashboard_id` (lo legge soltanto). Categorie via CompanyScope::byDatabase; dashboard/widget via i loro global scope `owned`. Niente esecuzione SQL / anteprime.

## Charts overview: beaker icon for project widgets
rebuild() adds 'hasProject' (=> $widget->project_id !== null) and 'projectName' (=> $widget->project?->name) to each chart entry; topLevelMasters() and the master/children queries eager-load ->with('project'). The blade renders a heroicon-o-beaker next to the chart title when hasProject, with title/aria-label "Studio: {projectName}" (or "Filtri di studio applicati" if the name is missing).

## Tabelle: heading, breadcrumb e sottotitolo
`$this->heading` è una proprietà pubblica che guida l'H1 nativo di Filament: livello `sections` → nome della categoria; livello `categories` → null → titolo statico "Tabelle". `getBreadcrumbs()` costruisce il breadcrumb nativo da `$this->trail` (ultima voce = pagina corrente, non cliccabile; ritorna [] se il trail ha una sola voce). `getSubheading()` dà un suggerimento per livello. Solo classi Tailwind presenti nella CSS precompilata di Filament nella blade (niente card raw con `rounded-xl border bg-white p-4` combos, niente grid responsive arbitrarie): si usano `<x-filament::section>` nativi + utility `divide-y`, `space-y-*`, colori `text-primary-*`/`text-gray-*`.
