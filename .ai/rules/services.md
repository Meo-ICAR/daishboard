---
paths:
  - app/Services/WidgetDatasetRunner.php
---

# Services

## Cohort filters: merged date + value shape
WidgetDatasetRunner::run($sql, $columnFilters) accepts ONE merged array of cohort-filter entries:
- date entries: {column: "patients.arruolato", from, to} -> injected as `alias`.`col` >= ? / <= ? before GROUP BY
- value entries: {column: "patient_visits.fumo_id", values: ["si","ex"]} -> injected as `alias`.`col` IN (?, ...) before GROUP BY
Both are only injected when the referenced cohort table actually appears in the widget SQL. Values keep empty string ("" is a real lookup value). Project::cohortFilters() returns this merged list (date_filters + value_filters). Form option lists for documented columns come from App\Support\CohortFilterCatalog (built from schema_legends; call ::flush() in tests after legend:sync).

## Cohort tables follow the active DataNavigator profile
The "cohort tables" (the ones WidgetDatasetRunner injects filters for, and CohortFilterCatalog / the ProjectForm builder read) are NOT hardcoded to patients/patient_visits anymore. They come from `App\Support\DataNavigatorProfile::cohortTables()` = the active profile's `tables` in config/data_navigator.php (fallback ['patients','patient_visits'] when the profile declares none). So changing `dbai` to another client DB (e.g. proforma -> mediatore profile) makes date/value filters resolve that DB's tables. `WidgetDatasetRunner::resolveCohortTableAliases()` matches those tables longest-first + `(?![a-z0-9_])` so `pratiches_statos` isn't read as `pratiches`. The old `WidgetDatasetRunner::COHORT_TABLES` / `CohortFilterCatalog::TABLES` consts were removed. `CohortFilterCatalog::dateColumnOptions()` now also offers any date/datetime-typed column (not just ones with a semantic `date_category`), since DateFieldSemanticsMap only maps the HIV cohort; those columns just get generic (non-semantic) presets. legend:sync already targets the active profile's tables. Tests pin the read-only DB via phpunit.xml (`DB_DATABASE_DBAI=hassisdadmin`, `DATA_NAVIGATOR_PROFILE=` empty) so the suite stays on the HIV cohort regardless of the dev's .env; force `config(['data_navigator.profile' => 'mediatore'])` in a test to exercise another profile.
