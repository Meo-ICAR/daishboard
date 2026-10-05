---
paths:
  - 'app/Filament/Resources/{LookupTables,SchemaLegends}/Pages/View*.php'
---

# Lookup Tables Schema Legends Pages

## Legenda: "Scarica Excel" su ViewLookupTable e ViewSchemaLegend
Entrambe le viste esportano con `App\Exports\WidgetDatasetExport` (export tabellare generico: headings + righe associative), non gated (download è read-only). ViewLookupTable ha un solo `Action::make('exportExcel')` in `getHeaderActions()`: esporta i valori (`$record->values ?: $record->liveValues()`, headings = key_column/label_column o "Valore"/"Etichetta") — non ha una tabella embedded, solo un modale "Valori". ViewSchemaLegend ne ha **due**, entrambe nell'header di pagina (`getHeaderActions()`), non nell'infolist (`Filament\Schemas\Components\Section::headerActions()` non è affidabile/testabile con gli helper standard `assertActionExists`/`callAction`, quindi va evitata per download di pagina): `downloadTableExcel` ("Download Excel") esegue `DB::connection($record->connection)->table($record->table_name)->get()` e scarica **tutti i record reali** della tabella collegata (headings = nomi campo dalla legenda, ordinati per `position`); `exportExcel` (spostato in `ColumnsRelationManager::table()->headerActions()`, riga toolbar della tabella "Campi") esporta invece i **campi della legenda stessa** (`$this->getOwnerRecord()->columns()` con position/name/data_type/nullable/comment/date_category/summary()/lookup). Filename: `Str::slug(table_name.'-valori'|'-legenda'|'-dati').'-'.now()->format('Ymd-His').'.xlsx'`. Tests: LegendExcelExportTest (Excel::fake + assertDownloaded).
