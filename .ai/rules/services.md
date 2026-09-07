---
paths:
  - app/Services/WidgetDatasetRunner.php
---

# Services

## Cohort filters: merged date + value shape
WidgetDatasetRunner::run($sql, $columnFilters) accepts ONE merged array of cohort-filter entries:
- date entries: {column: "patients.arruolato", from, to} -> injected as `alias`.`col` >= ? / <= ? before GROUP BY
- value entries: {column: "patient_visits.fumo_id", values: ["si","ex"]} -> injected as `alias`.`col` IN (?, ...) before GROUP BY
Both are only injected when the referenced cohort table (patients / patient_visits) actually appears in the widget SQL. Values keep empty string ("" is a real lookup value). Project::cohortFilters() returns this merged list (date_filters + value_filters). Form option lists for documented columns come from App\Support\CohortFilterCatalog (built from schema_legends; call ::flush() in tests after legend:sync).
