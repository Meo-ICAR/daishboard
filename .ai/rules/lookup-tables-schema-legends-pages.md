---
paths:
  - 'app/Filament/Resources/{LookupTables,SchemaLegends}/Pages/View*.php'
---

# Lookup Tables Schema Legends Pages

## Legenda: "Scarica Excel" su ViewLookupTable e ViewSchemaLegend
Both View pages have a `Action::make('exportExcel')` header action ("Scarica Excel", non gated — download è read-only) that reuses `App\Exports\WidgetDatasetExport` (export tabellare generico: headings + righe associative). ViewLookupTable esporta i valori (`$record->values ?: $record->liveValues()`, headings = key_column/label_column o "Valore"/"Etichetta"); ViewSchemaLegend esporta i campi della legenda (`$record->columns()` con position/name/data_type/nullable/comment/date_category/summary()/lookup). Filename: `Str::slug(table_name.'-valori'|'-legenda').'-'.now()->format('Ymd-His').'.xlsx'`. Tests: LegendExcelExportTest (Excel::fake + assertDownloaded).
