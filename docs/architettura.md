# Daishboard — Architettura e guida agli ampliamenti

Riferimento tecnico per estendere la piattaforma (anche in *vibe coding*: descrivi la
feature all'assistente, lui parte da qui).

**Regola d'oro**: prima di editare un file, apri `.ai/rules/index.md`, individua le
righe i cui glob coprono il percorso in gioco e leggi quei file di regole. Dopo ogni
modifica PHP: `vendor/bin/pint --dirty --format agent`, poi `php artisan test --compact`.

---

## 0. Cos'è

Strumento **generico** di analisi per **coorti cliniche**. Si collega in **sola lettura**
a un archivio clinico esterno che abbia (almeno) tabelle di **pazienti, visite,
trattamenti** e tabelle di lookup/dizionario. Produce dashboard, tabelle ricercabili e
grafici *riproducibili*, con:

- **studi** = insiemi di filtri di coorte applicati prima di ogni aggregazione;
- **drill-down**: da un totale aggregato all'elenco dei singoli pazienti che lo compongono;
- **assistente dati**: domanda in linguaggio naturale → query SQL di sola lettura;
- **legenda dello schema** + **catalogo lookup** generati per introspezione;
- **condivisione** via link pubblici e **export** Excel.

Istanza corrente: connessione `dbai` → database `hassisdadmin` (coorte HIV: `patients`,
`patient_visits`, ~30+ lookup). Lo stesso codice serve altri domini cambiando profilo +
puntando `dbai` altrove.

## 1. Stack

| | |
|---|---|
| Framework | Laravel 13, PHP 8.4 |
| Admin UI | Filament 5.7, pannello `admin` su `/admin` |
| DB app | connessione default (MySQL in prod, **SQLite `:memory:` nei test**) |
| DB clinico | connessione **`dbai`**, sola lettura, esterna |
| AI | NeuronAI (`neuron-core/neuron-ai`, `neuron-core/neuron-laravel`) + provider Anthropic |
| Auth | Filament login/registrazione/reset + Socialite (Google, Microsoft) |
| Excel | `pxlrbt/filament-excel` + `maatwebsite/excel` |
| Test | PHPUnit (`php artisan test`) |

Ambiente di sviluppo: repo su WSL Ubuntu-24.04, shell dell'assistente su Git Bash
Windows → usare `wsl -d ubuntu-24.04 bash -lc '…'`. Gli heredoc con virgolette annidate
si corrompono: scrivere file di script.

---

## 2. Multi-dominio — `config/data_navigator.php`

Un **profilo** descrive un dominio clinico servito da `dbai`.

```php
'profiles' => [
  'hiv' => [
    'databases' => ['hassisdadmin'],       // nomi DB serviti da questo profilo
    'tables'    => ['patients', 'patient_visits'],
    'identifier_column' => 'pazientecode', // sostituisce `id` nelle viste tabella
    'background' => '…', 'steps' => [...], 'output' => [...], // testo system prompt AI
  ],
  'mediatore' => [ 'databases' => ['proforma'], 'tables' => ['pratiches','provvigioni'], … ],
],
```

Risoluzione del profilo attivo (`DataNavigatorAgent::resolveProfile()` e
`App\Support\DataNavigatorProfile`):

1. `DATA_NAVIGATOR_PROFILE` env **solo se è una chiave valida** (altrimenti ignorato — es. vi è finito per errore un nome di database);
2. profilo il cui `databases` contiene `DB::connection('dbai')->getDatabaseName()`;
3. `data_navigator.default`.

`App\Support\DataNavigatorProfile`: `databaseName()`, `forCurrentDatabase()`,
`identifierColumn()`.

### Aggiungere un cliente / dominio

1. nuovo profilo in `config/data_navigator.php` (`databases`, `tables`, `identifier_column`, testo AI);
2. `.env` di quel deployment: `dbai` → il loro database;
3. `php artisan legend:sync` → documenta le `tables` del profilo e cataloga i lookup;
4. (opz.) `DATA_NAVIGATOR_PROFILE=<chiave>` per forzare.

---

## 3. Proprietà e visibilità dei record (governance)

### Ruoli — `App\Models\User`

- `isSuperAdmin()` = `is_admin && company_id === null`
- `isAdmin()` = `is_admin`
- `is_admin`: cast `boolean`, default DB `true`; i metodi fanno `(bool)` per tollerare l'istanza non ricaricata.

### Colonne

`dashboard_widgets`, `dashboards`, `projects`, `chat_messages`: `company_id` + `user_id`
(nullable). `users`: `company_id`, `is_admin`, **`project_id`**, **`dashboard_id`**.
`menu_categories` / `projects` / `schema_legends`: `database` (varchar).

### Lato creazione — `App\Models\Concerns\StampsOwnership`

Trait su `DashboardWidget`, `Dashboard`, `Project`. Hook `creating`, per ruolo di chi crea:

| Ruolo | `company_id` | `user_id` |
|---|---|---|
| superadmin | — | — |
| admin azienda | company dell'utente | — |
| utente normale | company dell'utente | id utente |

Valori **non-null** passati esplicitamente a `create()` sono preservati (`null` = "non impostato").
`bootStampsOwnership` convive con il `booted()` del modello.

### Lato lettura

- **Global scope `owned`** su `DashboardWidget` (in `booted()`): nessun utente → `0 = 1`;
  superadmin → nessun filtro; admin → `(company_id IS NULL OR = X)`; normale → quello
  **AND** `(user_id IS NULL OR = id)`. I record globali (colonne NULL) restano visibili a tutti.
- **`App\Support\CompanyScope::byOwner($query)`** — stessa logica di ruolo, applicata
  esplicitamente dove il global scope non basta: `DashboardChartsOverview::topLevelMasters()`,
  `DashboardWidgetsTable`, `DataAssistant`.
- **`CompanyScope::byDatabase($query, $col = 'database')`** — `(database IS NULL OR = company->database)`;
  nessun filtro se l'utente non ha company con database. Usato su `menu_categories`,
  `projects`, `schema_legends`.
- `Dashboard` ha un global scope `owned` attivo; quello di `Project` è disattivato (metodo `bootedx`).

### Ultima selezione — `User::rememberSelection()`

`users.project_id` / `dashboard_id` memorizzano l'ultima scelta, riproposta al login.

- `rememberSelection(int|false|null $projectId = null, int|false|null $dashboardId = null)` → `saveQuietly` (`null` = lascia, `false` = azzera);
- `DashboardChartsOverview` / `DashboardTablesOverview`: al `mount()` "pulito" prendono `dashboardId` da `auth()->user()->dashboard_id`; lo salvano a ogni cambio dashboard esplicito;
- `Project::storeCurrentFilters()` tiene `users.project_id` puntato allo studio corrente (`User::withoutGlobalScopes()->update`);
- `DashboardChartsOverview::mount()` preferisce `auth()->user()->project` a `Project::currentFor()`.

---

## 4. Esecuzione dei dataset — `App\Services\WidgetDatasetRunner`

`run(?string $sql, array $columnFilters = [])` → `{columns, rows, numericColumns, error}`.
Esegue su `dbai`. Solo `SELECT` (guardia con regex). Unico punto di verità condiviso da UI
Filament e vista pubblica.

`$columnFilters` = **elenco unito** di filtri di coorte:

- voce **data**: `{column: 'patients.arruolato', from, to}` → `` `alias`.`col` >= ? / <= ? ``
- voce **valore**: `{column: 'patient_visits.fumo_id', values: ['si','ex']}` → `` `alias`.`col` IN (?, …) ``
- iniettati **prima del `GROUP BY`** e **solo** se la tabella di coorte
  (`COHORT_TABLES = ['patients','patient_visits']`) compare nel SQL. La stringa vuota `''`
  è un valore lookup legittimo.

Metodi: `dateFilterMetadata($sql)`, `describeFilters($sql, $filters)`, `applyFilters`,
`injectCohortPredicate` (mascheramento a profondità di parentesi per trovare il confine
della clausola top-level), `resolveCohortTableAliases`.

Semantica date di coorte: `App\Enums\DateFieldCategory`, `App\Services\DateFieldSemanticsMap`,
`App\Services\ResearchDateRangeResolver` (preset per categoria), `App\Services\DateRangeResolver`
(preset generici).

`App\Support\CohortFilterCatalog` — costruisce le opzioni dei form (colonne data, preset,
colonne valore = lookup o `tinyint`/flag, valori ammessi) da `schema_legends`. Cache
statica; `::flush()` nei test dopo `legend:sync`. Consumato da `ProjectForm`.

`Project::cohortFilters()` = `array_merge(date_filters, value_filters)` filtrato a voci con `column`.

---

## 5. Legenda schema & catalogo lookup

### `php artisan legend:sync` — `App\Console\Commands\SyncSchemaLegend`

- documenta le tabelle del **profilo attivo** (fallback `config('legend.tables')`) in
  `schema_legends` + `schema_legend_columns`: `name, data_type, nullable, position,
  comment, date_category, date_ranges, foreign_key_name, lookup_table, lookup_key,
  lookup_label, lookup_values`, con `database` = nome DB collegato;
- cataloga **tutte le altre** tabelle `dbai` in `lookup_tables` (via
  `information_schema.TABLES`, esclude i pattern `config('legend.lookup_exclude')` + le
  tabelle entità), agganciando i valori se la tabella è piccola;
- collega automaticamente lookup ↔ colonne via pivot `lookup_table_column`
  (`syncWithoutDetaching`).

### `App\Services\TableSchemaInspector`

Introspezione DB con cache, connection-aware: `getDateFilterableColumns`,
`getAllColumnsWithComments`, `getTableComment`, `getForeignKeys`, `getLookupValues`.

### Modelli e UI

- `SchemaLegend` hasMany `SchemaLegendColumn`; `SchemaLegendColumn` belongsToMany
  `LookupTable` (`lookup_table_column`); `LookupTable` belongsToMany `columns`, `liveValues()`.
- `SchemaLegendResource` — vista legenda; cliccando un campo con lookup si apre
  `lookup-tables/{id}`.
- `LookupTableResource` — nav group **Lookup**; vede i valori; `ColumnsRelationManager`
  lega/slega la lookup ai campi di pazienti/visite (`isReadOnly()` → `false`).

---

## 6. `App\Models\DashboardWidget` — trasformazioni SQL

| Metodo | Cosa fa |
|---|---|
| `convertSqlStringToDrillDown($sql, $filters=[])` | rimuove `GROUP BY`/`HAVING`/`ORDER BY` top-level; spacchetta le voci aggregate della SELECT nel campo interno; se l'unica aggregata era `COUNT(*)`/`COUNT(1)` → `SELECT *`; aggiunge `col = 'v'` / `col IS NULL` da `$filters` (chiave = espressione SQL) |
| `selectDimensionExpressions($sql)` | `alias(minuscolo) => espressione SQL` per le voci **non** aggregate di una SELECT singola e raggruppata (solo `espr AS alias` e identificatori semplici; espressioni complesse senza `AS` ignorate) |
| `swapSelectIdentifier($sql, $identifier)` | riscrive **solo** le voci di primo livello che sono `id` / `t.id` (con eventuale `AS id`) → `t.<identifier>` |
| `splitTopLevel($list)` | split su virgola rispettando le parentesi |
| `convertToDrillDown(Builder, $filters)` | variante query-builder (poco usata) |

Relazioni: `dashboard`, `company`, `user`, `project`, `chatHistory`, `masterWidget`,
`detailWidgets` (self hasMany su `master_widget_id`), `shares`.
Colonne rilevanti: `master_widget_id`, `master_filter_column`, `type`, `query`, `settings`
(array), `grid_position` (array), `order`, `is_active`, `project_id`.

---

## 7. Viste tabella e grafico

### Trait `…\DashboardWidgets\Concerns\InteractsWithWidgetDataset`

Condiviso da `ViewDashboardWidget` e `ChartDashboardWidget`.

- `bootWidgetDataset($record)` — carica il widget (con `project`), imposta le prop
  `#[Locked]` (`recordId`, `widgetTitle`, `widgetType`, `widgetQuery`, `widgetProjectId`,
  `projectCohortFilters`), costruisce i metadati dei filtri data.
- `runQuery()` → `WidgetDatasetRunner::run($this->datasetQuery(), $this->cohortFilters())`.
- `datasetQuery()` — hook **sovrascrivibile** (default = `widgetQuery`).
- `cohortFilters()` = `[...$projectCohortFilters, ...$activeDateFilters()]` (tutto in AND).
- `dateFilterHeaderActions()` — modale "Filtro coorte" (repeater: `column`, `preset`, `from`, `to`).

### `ViewDashboardWidget` (`filament.resources.dashboard-widgets.pages.view-dashboard-widget`)

- Tabella **custom-data** via `->records(closure)` (non Eloquent). Parametri iniettati:
  `?string $sortColumn, ?string $sortDirection, int $page, int $recordsPerPage, ?string $search`.
  Ordinamento/ricerca/paginazione **gestiti nella closure**.
- **Ricerca**: ogni colonna **non numerica** ha `->searchable()`; il filtro effettivo è in
  `rowMatchesSearch($row, $search)` (sottostringa case-insensitive su tutte le colonne non numeriche).
- **Azioni**: tutte nell'**header della tabella** (`->headerActions($this->tableHeaderActions())`),
  **non** in `getHeaderActions()` → l'header di pagina (titolo + sottotitolo) resta a piena
  larghezza. Nessun `->heading()` sulla tabella (titolo già in breadcrumb + header pagina).
- **`id` → identificatore parlante**: `datasetQuery()` passa il SQL (widget e drill-down)
  in `swapSelectIdentifier` quando `DataNavigatorProfile::identifierColumn()` è valorizzato.
- **Drill-down master/dettaglio** (preesistente): la cella numerica apre la vista del widget
  figlio con query param `masterFilterColumn` / `masterFilterValue` / `MasterFilterField`.
- **Drill-down generato**: su widget aggregato (SELECT singola + `GROUP BY`) **senza figli**,
  la cella numerica ricarica la stessa pagina con `?drill=<base64url(JSON)>` = **lista** di
  `{label, expr, value}` per le dimensioni non numeriche della riga. `decodeDrill()` accetta
  anche la vecchia mappa `{expr: value}`. In drill: `datasetQuery()` =
  `convertSqlStringToDrillDown(widgetQuery, {expr:value})` + `swapSelectIdentifier` + `LIMIT 2000`.
  Sottotitolo = `label = value` (valore troncato a 60). Azione "Torna all'aggregato".
- **Export Excel**: `App\Exports\WidgetDatasetExport` (`FromArray, WithHeadings,
  ShouldAutoSize, WithTitle`) via `Excel::download(...)`.
- **Link pubblico**: azione `share` → `DashboardWidgetShare::create([... 'project_id' =>
  $widgetProjectId, 'parameters' => ['dateFilters' => activeDateFilters()], 'include_children',
  'expires_at' ])`. Rotte `shared.widget` / `shared.widget.child` (no auth, throttle) →
  `App\Http\Controllers\SharedWidgetController`, che applica
  `$share->project?->cohortFilters()` + `dateFilters` memorizzati.

### `DashboardWidgetChart` + `ChartDashboardWidget`

`ChartWidget` (Chart.js). Tipo di default da `dashboard_widget->type` via
`App\Support\ChartType` (`label()`, `icon()`, `resolveType()`), modificabile. Colori
variati per serie Y **e** per categoria X. Nota: il `normalizeDatasets` compilato deriva
`borderColor` solo dal `backgroundColor` del dataset → impostare array di colori a livello
di dataset.

---

## 8. Pagine di panoramica (`app/Filament/Pages/`, auto-discovered)

### `DashboardChartsOverview` — grafici

- `#[Url] ?int $master, ?int $dashboardId`.
- `topLevelMasters()` = `CompanyScope::byOwner(DashboardWidget::query())
  ->whereNull('master_widget_id')->where('is_active', true)
  ->whereRaw("COALESCE(LOWER(type),'') <> 'table'")->where('dashboard_id', …)`.
- Griglia di grafici master (3 per riga → `<style>` inline: le classi responsive arbitrarie
  di Tailwind **non** sono nella CSS precompilata di Filament). Click su un master → i suoi figli (`?master={id}`).
- Filtri di coorte dallo studio corrente; modale "Filtri studio" → `Project::storeCurrentFilters`.

### `DashboardTablesOverview` — nav "Tabelle"

- **Elenco** a drill-down (non esegue SQL). `#[Url] ?int $category` (0 = senza categoria),
  `?int $dashboardId`, `?int $master`. `level` ∈ `categories | dashboards | masters | children`.
- Solo widget `COALESCE(LOWER(type),'') = 'table'`; categorie/dashboard filtrate con
  `whereHas` a quelle che contengono almeno una tabella.
- `rebuild()` risale la catena dal parametro più profondo; memorizza `users.dashboard_id`.
- Blade: `<ul>`; le righe widget linkano a `ViewDashboardWidget`; i master con figli hanno
  un link "N figli".

### `DashboardWidgetsTable` (risorsa lista)

- `->modifyQueryUsing(fn ($q) => CompanyScope::byOwner($q))`.
- Colonna `type`: link a `view` se `strtolower(type) === 'table'`, altrimenti a `chart`.
- Filtri: `dashboard_id` (default = `auth()->user()?->dashboard_id` ?? prima dashboard);
  `is_table` (`SelectFilter`, placeholder "Tutti", opzioni `table` / `not_table` →
  `whereRaw("COALESCE(LOWER(type),'') = / <> 'table'")`).

---

## 9. Assistente dati

### `App\Neuron\DataNavigatorAgent`

- estende `NeuronAI\Agent\Agent`. **Sola lettura**: `tools()` = **solo**
  `MySQLSchemaTool::make($pdodbai)` + `MySQLSelectTool::make($pdodbai)`. **MAI**
  `MySQLToolkit` (include il write tool).
- `provider()` = `new Anthropic(key: env('ANTHROPIC_API_KEY'), model:
  env('ANTHROPIC_MODEL','claude-sonnet-4-6'), max_tokens: 8192)` (eccezione se chiave vuota).
- `instructions()` = dal profilo attivo: `prompt` grezzo (segnaposti `{schema}`,
  `{database}`, `{connection}`) **oppure** `SystemPrompt(background:[profilo.background,
  schemaSection()], steps:[…], output:[…])`.
- `schemaSection()` — da `SchemaLegend::whereIn('table_name', profilo.tables)
  ->where('database', $db)->orWhereNull('database')`: nomi reali, commenti, `[DATE reale]`,
  valori lookup. Testo di fallback se la legenda non è sincronizzata.
- `chatHistory()` = `EloquentChatHistory(threadId, modelClass: App\Models\ChatMessage,
  contextWindow: 150000)`.

### `App\Models\ChatMessage extends NeuronAI\Laravel\Models\ChatMessage`

`$table = 'chat_messages'`; `content`/`meta` cast `array`; `company_id`/`user_id` auto in
`creating` da auth. **NeuronAI salva i blocchi di testo con chiave `content`** (non `text`)
→ `DataAssistant::renderContent()` legge `$block['content']`.

### `App\Filament\Pages\DataAssistant`

- Thread per utente: prefisso `u{userId}-{ulid}`; `#[Url]` + sessione.
- Sidebar `recentThreads()`: `thread_id` distinti con `user_id = auth id`,
  `MAX(created_at)`, conteggio, etichetta dal primo messaggio utente. Bottoni con
  `wire:key` + `wire:click="openThread('…')"`.
- `openThread($thread)` valida la proprietà poi `loadMessages()`.
- "Crea widget" sotto un messaggio assistente → inserisce l'SQL mostrato in
  `dashboard_widgets->query`, `title` = la domanda. `extractSql()` legge i blocchi ```sql
  ``` e il fallback `` (WITH|SELECT) … ; ``.

---

## 10. Autenticazione

- Filament: `->login()->registration()->passwordReset()`.
- Socialite: `dutchcodingcompany/filament-socialite` (plugin nel pannello) + provider
  `socialiteproviders/google`, `socialiteproviders/microsoft` (e `openidconnect`).
  I provider SocialiteProviders **non** sono driver nativi: i listener per
  `SocialiteProviders\Manager\SocialiteWasCalled` sono registrati in
  `AppServiceProvider::boot()` (`MicrosoftExtendSocialite`, `GoogleExtendSocialite`).
- `config/services.php` → blocchi `microsoft` (con `tenant` = `MICROSOFT_TENANT_ID`) e `google`.
- Callback: `admin/oauth/callback/{provider}`.

---

## 11. Trappole note

- **Global scope su `User`**: mai un global scope che chiama `auth()->user()` — l'user
  provider ricarica `User` durante l'autenticazione → ricorsione / loop di login. La
  visibilità utenti va in `UserResource::getEloquentQuery()`, non in un global scope.
- **Modelli in Livewire**: non serializzare un modello Eloquent in una pagina Livewire
  (TypeError in re-hydration). Usare `#[Locked] public int|string $recordId` + prop scalari.
- **Filament custom-data table** (`->records()`): ricerca/ordinamento/paginazione a carico
  della closure, non di Filament.
- **Tailwind + Filament**: classi arbitrarie/responsive non presenti nella CSS
  precompilata del pannello → `<style>` inline.
- **Livewire `#[Url]` int**: `0` resta in URL; `null` sparisce con `keep: false`.
- **Test**: la connessione default è SQLite `:memory:` + `RefreshDatabase`; i test di
  `WidgetDatasetRunner` / legenda colpiscono il **vero** MySQL `dbai` (`hassisdadmin`).
- **`is_admin` null**: `DashboardWidget::creating` chiama `auth()->user()?->isAdmin()` —
  `is_admin` non deve essere `null` sull'istanza in memoria (cast boolean + default DB +
  `(bool)` nei metodi lo gestiscono).
- **NeuronAI content**: chiave `content`, non `text` (vedi §9).
- **`DATA_NAVIGATOR_PROFILE`**: se non è una chiave di profilo valida viene ignorato (non lancia).

---

## 12. Mappa dei file

```
config/
  data_navigator.php        profili di dominio (tabelle, identifier_column, prompt AI)
  legend.php                connessione, tabelle fallback, esclusioni lookup

app/Support/
  DataNavigatorProfile.php  profilo per il DB collegato; identifierColumn()
  CompanyScope.php          byOwner() (ruolo-aware), byDatabase()
  CohortFilterCatalog.php   opzioni form filtri di coorte da schema_legends
  ChartType.php             label/icona/tipo grafico

app/Services/
  WidgetDatasetRunner.php   esegue il SQL su dbai + inietta i filtri di coorte
  TableSchemaInspector.php  introspezione DB (cache)
  DateFieldSemanticsMap.php / ResearchDateRangeResolver.php / DateRangeResolver.php

app/Models/
  DashboardWidget.php       trasformazioni SQL (drill-down, swapSelectIdentifier)
  Dashboard.php / Project.php / User.php / ChatMessage.php
  SchemaLegend.php / SchemaLegendColumn.php / LookupTable.php
  DashboardWidgetShare.php
  Concerns/StampsOwnership.php

app/Console/Commands/
  SyncSchemaLegend.php      legend:sync

app/Neuron/
  DataNavigatorAgent.php    agente NeuronAI di sola lettura

app/Filament/
  Pages/DataAssistant.php
  Pages/DashboardChartsOverview.php
  Pages/DashboardTablesOverview.php
  Resources/DashboardWidgets/
    Pages/ViewDashboardWidget.php      tabella + drill-down + ricerca + share + export
    Pages/ChartDashboardWidget.php
    Concerns/InteractsWithWidgetDataset.php
    Schemas/DashboardWidgetForm.php    (select project_id filtrata per database dashboard)
    Tables/DashboardWidgetsTable.php
  Resources/SchemaLegends/ , Resources/LookupTables/ , Resources/Projects/
  Widgets/DashboardWidgetChart.php

app/Http/Controllers/SharedWidgetController.php   vista pubblica (no auth)
app/Exports/WidgetDatasetExport.php

.ai/rules/                  regole per-percorso (leggere index.md prima di editare)
```

---

## 13. Ricette di ampliamento (partenze per il vibe coding)

| Obiettivo | Dove intervenire |
|---|---|
| Nuovo dominio/cliente clinico | profilo in `config/data_navigator.php` + `legend:sync` |
| Nuovo tipo di grafico | `App\Support\ChartType` + `DashboardWidgetChart::resolveType` |
| Nuovo tipo di filtro di coorte | `WidgetDatasetRunner::applyFilters` + `CohortFilterCatalog` + `ProjectForm` |
| Nuova pagina/voce di menu | classe in `app/Filament/Pages/` (auto-discovered) |
| Cambiare la sostituzione `id → identificatore` | `DataNavigatorProfile` + `DashboardWidget::swapSelectIdentifier` |
| Nuova regola di dominio per l'assistente | `background`/`steps`/`output` del profilo in `config/data_navigator.php` |
| Nuovo scoping di visibilità | `CompanyScope` + eventuale global scope `owned` del modello |
| Nuova azione sulla vista tabella | `ViewDashboardWidget::tableHeaderActions()` |

Dopo ogni modifica: `vendor/bin/pint --dirty --format agent` → `php artisan test --compact`.
Registrare le decisioni durature con `record-rule` (Boost), non nella memoria personale.
