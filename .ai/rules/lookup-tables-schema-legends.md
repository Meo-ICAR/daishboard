---
paths:
  - 'app/Filament/Resources/{LookupTables,SchemaLegends}/**'
---

# Lookup Tables Schema Legends

## Legenda: sync + edit are superadmin-only
Codifiche (LookupTableResource) and Dati (SchemaLegendResource) are read-only for everyone except superadmin. Both List pages' "Sincronizza" header action (`Action::make('sync')` -> legend:sync) and every EditAction (table recordActions + the View pages' header) carry `->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false)`. Both resources also override `canEdit(Model $record)` to the same check so the /edit route is blocked directly. `canCreate()` is already false. If you add a new action that mutates the catalog, gate it the same way.
