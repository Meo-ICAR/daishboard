---
paths:
  - 'app/Filament/Resources/{LookupTables,SchemaLegends}/**'
---

# Lookup Tables Schema Legends

## Legenda: sync + edit are superadmin-only
Codifiche (LookupTableResource) and Dati (SchemaLegendResource) are read-only for everyone except superadmin. Both List pages' "Sincronizza" header action (`Action::make('sync')` -> legend:sync) and every EditAction (table recordActions + the View pages' header) carry `->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false)`. Both resources also override `canEdit(Model $record)` to the same check so the /edit route is blocked directly. `canCreate()` is already false. If you add a new action that mutates the catalog, gate it the same way.

## `lookup_tables.is_data_table`: manual superadmin classification, never touched by legend:sync
`legend:sync` catalogues **every** non-entity table in `dbai` as a "lookup", including junction/relation tables (e.g. `client_pratiches`, a pratica↔cliente pivot) that aren't real value dictionaries — it has no way to tell the two apart automatically. `is_data_table` (boolean, default `false`) is a manual override the superadmin sets in `admin/lookup-tables` (a `ToggleColumn` in `LookupTablesTable`, `->disabled()` for non-superadmins, checked server-side too since `updateTableColumnState()` re-verifies `$column->isDisabled()`) to mark a catalogued table as "dati" rather than a genuine lookup. Unlike `is_dictionary` (recomputed on every sync from `row_count <= lookup_enum_max`), `SyncSchemaLegend::syncLookups()` deliberately **excludes** `is_data_table` from its `updateOrCreate()` payload, so a manual choice survives re-syncs — never add it to that payload. Filtered via `TernaryFilter::make('is_data_table')` (trueLabel "Solo dati" / falseLabel "Solo lookup") on the same list.
