<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Cruscotti del dominio "mediatore creditizio" (database `proforma`): pipeline
 * delle pratiche, analisi provvigionale e riconciliazione ENASARCO/OAM.
 *
 * Il seeder è una SINCRONIZZAZIONE: rilanciandolo aggiorna in place le query e i
 * tipi dei widget delle sue dashboard (chiave: dashboard + titolo) e rimuove i
 * widget non più previsti dal blueprint. Le query con parametro di input
 * (`:id_pratica`, `:denominazione_banca`, `:competenza`/`:trimestre`, ...) sono
 * widget figli di drill-down (`master_widget_id` + `master_filter_column`):
 * si aprono cliccando la cella numerica del master. Vedi config/data_navigator.php
 * (profilo `mediatore`, §5) per la convenzione dei parametri.
 *
 * Le dashboard hanno `database = 'proforma'` per lo scoping di
 * App\Support\CompanyScope::byDatabase(). I widget "di dominio" hanno
 * `company_id` NULL (contenuto globale); "Gestione Provvigioni & Fatturazione"
 * porta `company_id = 2` (rework dei widget storici della company 1).
 */
class MediatoreDashboardSeeder extends Seeder
{
    private const DATABASE = 'proforma';

    public function run(): void
    {
        $now = now();

        $categories = [
            'Produzione' => $this->categoryId('Produzione'),
            'Contabilita' => $this->categoryId('Contabilita'),
        ];

        foreach ($this->blueprint() as $order => $group) {
            $companyId = $group['company_id'] ?? null;

            $dashboardId = DB::table('dashboards')
                ->where('title', $group['dashboard'])
                ->where('database', self::DATABASE)
                ->when($companyId === null, fn ($q) => $q->whereNull('company_id'))
                ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
                ->value('id');

            if ($dashboardId === null) {
                $dashboardId = DB::table('dashboards')->insertGetId([
                    'user_id' => null,
                    'company_id' => $companyId,
                    'database' => self::DATABASE,
                    'menu_category_id' => $categories[$group['category']],
                    'title' => $group['dashboard'],
                    'description' => $group['description'],
                    'icon' => $group['icon'],
                    'order' => $order + 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('dashboards')->where('id', $dashboardId)->update([
                    'description' => $group['description'],
                    'icon' => $group['icon'],
                    'menu_category_id' => $categories[$group['category']],
                    'updated_at' => $now,
                ]);
            }

            $keptTitles = [];

            foreach ($group['widgets'] as $position => $widget) {
                $masterId = $this->syncWidget($dashboardId, $companyId, null, $widget, $position + 1, $now);
                $keptTitles[] = $widget['title'];

                foreach ($widget['children'] ?? [] as $childPosition => $child) {
                    $this->syncWidget($dashboardId, $companyId, $masterId, [
                        'title' => $child['title'],
                        'type' => $child['type'],
                        'query' => $child['query'],
                        'master_filter_column' => $child['filter_column'],
                    ], $childPosition + 1, $now);
                    $keptTitles[] = $child['title'];
                }
            }

            // Rimuove i widget di questa dashboard non più nel blueprint
            // (solo quelli gestiti dal seeder: senza riferimento a una chat).
            DB::table('dashboard_widgets')
                ->where('dashboard_id', $dashboardId)
                ->whereNull('chat_history_id')
                ->whereNotIn('title', $keptTitles)
                ->delete();
        }
    }

    /**
     * Inserisce o aggiorna un widget, identificato da dashboard + titolo (+ ruolo
     * master/figlio). Ritorna l'id.
     *
     * @param  array{title: string, type: string, query: string, master_filter_column?: ?string}  $w
     */
    private function syncWidget(int $dashboardId, ?int $companyId, ?int $masterId, array $w, int $order, mixed $now): int
    {
        $existing = DB::table('dashboard_widgets')
            ->where('dashboard_id', $dashboardId)
            ->where('title', $w['title'])
            ->when($masterId === null, fn ($q) => $q->whereNull('master_widget_id'))
            ->when($masterId !== null, fn ($q) => $q->where('master_widget_id', $masterId))
            ->first();

        $data = [
            'company_id' => $companyId,
            'master_widget_id' => $masterId,
            'master_filter_column' => $w['master_filter_column'] ?? null,
            'title' => $w['title'],
            'type' => $w['type'],
            'query' => $w['query'],
            'order' => $order,
            'is_active' => true,
            'updated_at' => $now,
        ];

        if ($existing !== null) {
            DB::table('dashboard_widgets')->where('id', $existing->id)->update($data);

            return (int) $existing->id;
        }

        return DB::table('dashboard_widgets')->insertGetId([
            ...$data,
            'dashboard_id' => $dashboardId,
            'user_id' => null,
            'project_id' => null,
            'created_at' => $now,
        ]);
    }

    private function categoryId(string $name): int
    {
        return DB::table('menu_categories')->where('name', $name)->value('id')
            ?? DB::table('menu_categories')->insertGetId([
                'name' => $name,
                'order' => 0,
                'is_active' => true,
            ]);
    }

    /**
     * @return array<int, array{dashboard: string, description: string, icon: string, category: string, company_id?: int, widgets: array<int, array<string, mixed>>}>
     */
    private function blueprint(): array
    {
        return [
            [
                'dashboard' => 'Pipeline & SLA Pratiche',
                'description' => 'Pratiche in istruttoria, deliberate ed erogate con giorni di giacenza, code di liquidazione, tempi medi per banca e prodotto e produzione erogata nel tempo.',
                'icon' => 'heroicon-o-inbox-stack',
                'category' => 'Produzione',
                'widgets' => [
                    [
                        'title' => 'Pratiche per fase operativa',
                        'type' => 'bar',
                        'query' => <<<'SQL'
SELECT
    CASE
        WHEN erogated_at IS NOT NULL THEN '4 · Erogata'
        WHEN rejected_at IS NOT NULL THEN '5 · Rifiutata'
        WHEN approved_at IS NOT NULL THEN '3 · Deliberata'
        WHEN sended_at IS NOT NULL THEN '2 · In istruttoria'
        ELSE '1 · Caricata'
    END AS fase,
    COUNT(id) AS numero_pratiche,
    SUM(COALESCE(erogato, amount)) AS importo_totale
FROM pratiches
GROUP BY fase
ORDER BY fase
SQL,
                    ],
                    [
                        'title' => 'Pratiche in istruttoria — giorni di giacenza',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    id AS id_pratica,
    codice_pratica,
    denominazione_agente,
    denominazione_banca,
    tipo_prodotto,
    sended_at AS data_invio,
    amount AS importo_richiesto,
    DATEDIFF(CURRENT_DATE, sended_at) AS giorni_in_istruttoria
FROM pratiches
WHERE sended_at IS NOT NULL
  AND approved_at IS NULL
  AND erogated_at IS NULL
  AND rejected_at IS NULL
ORDER BY sended_at ASC
LIMIT 1000
SQL,
                    ],
                    [
                        'title' => 'Istruttoria — volume e attesa media per banca',
                        'type' => 'bar',
                        'query' => <<<'SQL'
SELECT
    denominazione_banca,
    COUNT(id) AS numero_pratiche,
    SUM(amount) AS totale_importo_in_istruttoria,
    ROUND(AVG(DATEDIFF(CURRENT_DATE, sended_at)), 1) AS media_giorni_attesa
FROM pratiches
WHERE sended_at IS NOT NULL
  AND approved_at IS NULL
  AND erogated_at IS NULL
  AND rejected_at IS NULL
GROUP BY denominazione_banca
ORDER BY totale_importo_in_istruttoria DESC
LIMIT 20
SQL,
                    ],
                    [
                        'title' => 'Criticità — pratiche bloccate in istruttoria da oltre 30 giorni',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    id AS id_pratica,
    codice_pratica,
    denominazione_banca,
    denominazione_agente,
    tipo_prodotto,
    sended_at,
    amount AS importo_richiesto,
    DATEDIFF(CURRENT_DATE, sended_at) AS giorni_attesa
FROM pratiches
WHERE sended_at IS NOT NULL
  AND approved_at IS NULL
  AND erogated_at IS NULL
  AND rejected_at IS NULL
  AND DATEDIFF(CURRENT_DATE, sended_at) > 30
ORDER BY giorni_attesa DESC
LIMIT 1000
SQL,
                    ],
                    [
                        'title' => 'Coda di liquidazione — deliberate non ancora erogate',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    id AS id_pratica,
    codice_pratica,
    denominazione_banca,
    denominazione_agente,
    tipo_prodotto,
    approved_at AS data_delibera,
    amount AS importo_approvato,
    DATEDIFF(CURRENT_DATE, approved_at) AS giorni_da_approvazione
FROM pratiches
WHERE approved_at IS NOT NULL
  AND erogated_at IS NULL
  AND rejected_at IS NULL
ORDER BY approved_at ASC
LIMIT 1000
SQL,
                    ],
                    [
                        'title' => 'SLA di delibera per banca',
                        'type' => 'bar',
                        'query' => <<<'SQL'
SELECT
    denominazione_banca,
    COUNT(id) AS totale_pratiche_approvate,
    SUM(amount) AS montante_totale_approvato,
    ROUND(AVG(DATEDIFF(approved_at, sended_at)), 1) AS media_giorni_delibera,
    MIN(DATEDIFF(approved_at, sended_at)) AS min_giorni,
    MAX(DATEDIFF(approved_at, sended_at)) AS max_giorni
FROM pratiches
WHERE approved_at IS NOT NULL
  AND sended_at IS NOT NULL
GROUP BY denominazione_banca
ORDER BY montante_totale_approvato DESC
LIMIT 20
SQL,
                    ],
                    [
                        'title' => 'Erogati — elenco e ciclo di vita totale della pratica',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    id AS id_pratica,
    codice_pratica,
    denominazione_agente,
    denominazione_banca,
    tipo_prodotto,
    data_inserimento_pratica,
    erogated_at AS data_erogazione,
    erogato AS importo_erogato,
    rata,
    nrate,
    DATEDIFF(erogated_at, data_inserimento_pratica) AS giorni_totali_lavorazione
FROM pratiches
WHERE erogated_at IS NOT NULL
ORDER BY erogated_at DESC
LIMIT 1000
SQL,
                    ],
                    [
                        'title' => 'Produzione erogata per mese',
                        'type' => 'bar',
                        'query' => <<<'SQL'
SELECT
    DATE_FORMAT(erogated_at, '%Y-%m') AS mese,
    COUNT(id) AS pratiche_erogate,
    SUM(erogato) AS importo_erogato
FROM pratiches
WHERE erogated_at IS NOT NULL
GROUP BY DATE_FORMAT(erogated_at, '%Y-%m')
ORDER BY mese
SQL,
                    ],
                    [
                        'title' => 'SLA intermedi per fase e tipologia prodotto',
                        'type' => 'bar',
                        'query' => <<<'SQL'
SELECT
    tipo_prodotto,
    COUNT(id) AS pratiche_erogate,
    ROUND(AVG(DATEDIFF(sended_at, data_inserimento_pratica)), 1) AS avg_giorni_caricamento_invio,
    ROUND(AVG(DATEDIFF(approved_at, sended_at)), 1) AS avg_giorni_istruttoria,
    ROUND(AVG(DATEDIFF(erogated_at, approved_at)), 1) AS avg_giorni_delibera_erogazione,
    ROUND(AVG(DATEDIFF(erogated_at, data_inserimento_pratica)), 1) AS avg_giorni_totali
FROM pratiches
WHERE erogated_at IS NOT NULL
  AND sended_at IS NOT NULL
  AND approved_at IS NOT NULL
GROUP BY tipo_prodotto
ORDER BY pratiche_erogate DESC
LIMIT 20
SQL,
                    ],
                ],
            ],
            [
                'dashboard' => 'Provvigioni & Redditività',
                'description' => 'Ricavo netto per pratica e per banca/istituto, provvigioni per stato pipeline e stato del compenso, provvigioni passive degli agenti (diretta vs coordinamento) e attivo da incassare.',
                'icon' => 'heroicon-o-banknotes',
                'category' => 'Contabilita',
                'widgets' => [
                    [
                        'title' => 'Ricavo netto per singola pratica',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    p.id AS id_pratica,
    p.codice_pratica,
    p.denominazione_banca,
    p.denominazione_agente,
    p.erogato AS importo_erogato,
    COALESCE(SUM(CASE WHEN pr.entrata_uscita = 'Entrata' THEN pr.importo ELSE 0 END), 0) AS totale_entrata_banca,
    COALESCE(SUM(CASE WHEN pr.entrata_uscita = 'Uscita' THEN pr.importo ELSE 0 END), 0) AS totale_uscita_agente,
    (COALESCE(SUM(CASE WHEN pr.entrata_uscita = 'Entrata' THEN pr.importo ELSE 0 END), 0) -
     COALESCE(SUM(CASE WHEN pr.entrata_uscita = 'Uscita' THEN pr.importo ELSE 0 END), 0)) AS ricavo_netto
FROM pratiches p
INNER JOIN provvigioni pr ON pr.id_pratica = p.id
WHERE pr.annullato = 0
  AND pr.deleted_at IS NULL
GROUP BY p.id, p.codice_pratica, p.denominazione_banca, p.denominazione_agente, p.erogato
ORDER BY ricavo_netto DESC
LIMIT 1000
SQL,
                        'children' => [
                            [
                                'title' => 'Dettaglio righe provvigionali della pratica',
                                'type' => 'table',
                                'filter_column' => 'ricavo_netto',
                                'query' => <<<'SQL'
-- Parametro di input: :id_pratica
SELECT
    pr.id AS id_provvigione,
    pr.entrata_uscita,
    pr.tipo,
    pr.importo,
    pr.denominazione_riferimento,
    pr.coordinamento,
    pr.stato,
    pr.status_compenso,
    pr.n_fattura,
    pr.data_fattura
FROM provvigioni pr
WHERE pr.id_pratica = :id_pratica
  AND pr.annullato = 0
  AND pr.deleted_at IS NULL
ORDER BY pr.entrata_uscita ASC, pr.importo DESC
SQL,
                            ],
                        ],
                    ],
                    [
                        'title' => 'Redditività e margine netto per banca',
                        'type' => 'bar',
                        'query' => <<<'SQL'
SELECT
    p.denominazione_banca,
    COUNT(DISTINCT p.id) AS pratiche_totali,
    COALESCE(SUM(CASE WHEN pr.entrata_uscita = 'Entrata' THEN pr.importo ELSE 0 END), 0) AS totale_provvigioni_attive,
    COALESCE(SUM(CASE WHEN pr.entrata_uscita = 'Uscita' THEN pr.importo ELSE 0 END), 0) AS totale_provvigioni_passive,
    (COALESCE(SUM(CASE WHEN pr.entrata_uscita = 'Entrata' THEN pr.importo ELSE 0 END), 0) -
     COALESCE(SUM(CASE WHEN pr.entrata_uscita = 'Uscita' THEN pr.importo ELSE 0 END), 0)) AS margine_netto_totale
FROM pratiches p
INNER JOIN provvigioni pr ON pr.id_pratica = p.id
WHERE pr.annullato = 0
  AND pr.deleted_at IS NULL
GROUP BY p.denominazione_banca
ORDER BY margine_netto_totale DESC
LIMIT 20
SQL,
                        'children' => [
                            [
                                'title' => 'Provvigioni attive "da lavorare" per banca',
                                'type' => 'table',
                                'filter_column' => 'totale_provvigioni_attive',
                                'query' => <<<'SQL'
-- Parametro di input: :denominazione_banca
SELECT
    pr.id AS id_provvigione,
    p.id AS id_pratica,
    p.codice_pratica,
    p.erogated_at AS data_erogazione_pratica,
    pr.importo AS importo_provvigione,
    pr.tipo AS tipo_provvigione
FROM provvigioni pr
INNER JOIN pratiches p ON pr.id_pratica = p.id
WHERE pr.entrata_uscita = 'Entrata'
  AND pr.annullato = 0
  AND pr.deleted_at IS NULL
  AND pr.proforma_id IS NULL
  AND pr.fattura_id IS NULL
  AND pr.data_fattura IS NULL
  AND p.denominazione_banca = :denominazione_banca
ORDER BY p.erogated_at ASC
LIMIT 1000
SQL,
                            ],
                        ],
                    ],
                    [
                        'title' => 'Provvigioni per istituto finanziario',
                        'type' => 'bar',
                        'query' => <<<'SQL'
SELECT
    istituto_finanziario,
    COUNT(id) AS numero_provvigioni,
    COALESCE(SUM(CASE WHEN entrata_uscita = 'Entrata' THEN importo ELSE 0 END), 0) AS provvigioni_attive,
    COALESCE(SUM(CASE WHEN entrata_uscita = 'Uscita' THEN importo ELSE 0 END), 0) AS provvigioni_passive,
    (COALESCE(SUM(CASE WHEN entrata_uscita = 'Entrata' THEN importo ELSE 0 END), 0) -
     COALESCE(SUM(CASE WHEN entrata_uscita = 'Uscita' THEN importo ELSE 0 END), 0)) AS margine_netto
FROM provvigioni
WHERE annullato = 0
  AND deleted_at IS NULL
GROUP BY istituto_finanziario
ORDER BY margine_netto DESC
LIMIT 30
SQL,
                    ],
                    [
                        'title' => 'Provvigioni per stato pipeline',
                        'type' => 'pie',
                        'query' => <<<'SQL'
SELECT
    stato,
    COUNT(id) AS numero_provvigioni,
    SUM(importo) AS importo_totale
FROM provvigioni
WHERE annullato = 0
  AND deleted_at IS NULL
GROUP BY stato
ORDER BY importo_totale DESC
SQL,
                    ],
                    [
                        'title' => 'Provvigioni per stato del compenso',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    status_compenso,
    COUNT(id) AS numero_provvigioni,
    COALESCE(SUM(CASE WHEN entrata_uscita = 'Entrata' THEN importo ELSE 0 END), 0) AS attive,
    COALESCE(SUM(CASE WHEN entrata_uscita = 'Uscita' THEN importo ELSE 0 END), 0) AS passive
FROM provvigioni
WHERE annullato = 0
  AND deleted_at IS NULL
GROUP BY status_compenso
ORDER BY numero_provvigioni DESC
SQL,
                    ],
                    [
                        'title' => 'Provvigioni passive agenti — diretta vs coordinamento rete',
                        'type' => 'bar',
                        'query' => <<<'SQL'
SELECT
    denominazione_riferimento AS agente,
    COUNT(id) AS totale_compensi,
    SUM(CASE WHEN coordinamento = 1 THEN importo ELSE 0 END) AS compenso_coordinamento,
    SUM(CASE WHEN coordinamento = 0 OR coordinamento IS NULL THEN importo ELSE 0 END) AS compenso_diretto,
    SUM(importo) AS totale_provvigione_passiva
FROM provvigioni
WHERE entrata_uscita = 'Uscita'
  AND annullato = 0
  AND deleted_at IS NULL
GROUP BY denominazione_riferimento
ORDER BY totale_provvigione_passiva DESC
LIMIT 20
SQL,
                    ],
                    [
                        'title' => 'Provvigioni attive da incassare per istituto',
                        'type' => 'bar',
                        'query' => <<<'SQL'
SELECT
    istituto_finanziario,
    COUNT(id) AS numero_provvigioni,
    SUM(importo) AS importo_da_incassare
FROM provvigioni
WHERE entrata_uscita = 'Entrata'
  AND stato <> 'Pagato'
  AND annullato = 0
  AND deleted_at IS NULL
GROUP BY istituto_finanziario
ORDER BY importo_da_incassare DESC
LIMIT 30
SQL,
                    ],
                ],
            ],
            [
                'dashboard' => 'ENASARCO & Disallineamenti OAM',
                'description' => 'Scadenziario e riepilogo dei contributi ENASARCO per trimestre e per agente, e pratiche il cui trimestre di competenza OAM (erogazione) non coincide con quello ENASARCO (fattura agente).',
                'icon' => 'heroicon-o-calendar-days',
                'category' => 'Contabilita',
                'widgets' => [
                    [
                        'title' => 'Scadenziario trimestrale versamenti ENASARCO',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    competenza AS anno,
    Trimestre,
    COUNT(DISTINCT produttore) AS totale_agenti,
    SUM(montante) AS imponibile_provvigionale,
    SUM(contributo) AS totale_enasarco_da_versare,
    CASE Trimestre
        WHEN 1 THEN STR_TO_DATE(CONCAT(competenza, '-04-10'), '%Y-%m-%d')
        WHEN 2 THEN STR_TO_DATE(CONCAT(competenza, '-07-10'), '%Y-%m-%d')
        WHEN 3 THEN STR_TO_DATE(CONCAT(competenza, '-10-10'), '%Y-%m-%d')
        WHEN 4 THEN STR_TO_DATE(CONCAT(competenza + 1, '-01-10'), '%Y-%m-%d')
    END AS data_scadenza_versamento
FROM venasarcotrimestre
WHERE competenza = YEAR(CURDATE())
  AND enasarco NOT IN ('no', 'societa')
GROUP BY competenza, Trimestre
ORDER BY Trimestre ASC
SQL,
                        'children' => [
                            [
                                'title' => 'Contributi ENASARCO dovuti per agente sul trimestre',
                                'type' => 'table',
                                'filter_column' => 'totale_enasarco_da_versare',
                                'query' => <<<'SQL'
-- Parametri di input: :competenza, :trimestre
SELECT
    id,
    produttore AS agente,
    enasarco AS tipo_mandato,
    montante AS imponibile_trimestre,
    contributo AS importo_enasarco
FROM venasarcotrimestre
WHERE competenza = :competenza
  AND Trimestre = :trimestre
  AND enasarco NOT IN ('no', 'societa')
ORDER BY contributo DESC
LIMIT 1000
SQL,
                            ],
                        ],
                    ],
                    [
                        'title' => 'Contributo ENASARCO per trimestre',
                        'type' => 'bar',
                        'query' => <<<'SQL'
SELECT
    CONCAT(competenza, '-Q', Trimestre) AS periodo,
    COUNT(DISTINCT produttore) AS agenti,
    SUM(montante) AS imponibile,
    SUM(contributo) AS contributo_enasarco
FROM venasarcotrimestre
WHERE enasarco NOT IN ('no', 'societa')
GROUP BY competenza, Trimestre
ORDER BY competenza, Trimestre
SQL,
                    ],
                    [
                        'title' => 'ENASARCO per agente — anno corrente',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    produttore AS agente,
    enasarco AS tipo_mandato,
    COUNT(DISTINCT Trimestre) AS trimestri,
    SUM(montante) AS imponibile_anno,
    SUM(contributo) AS contributo_anno
FROM venasarcotrimestre
WHERE competenza = YEAR(CURDATE())
  AND enasarco NOT IN ('no', 'societa')
GROUP BY produttore, enasarco
ORDER BY contributo_anno DESC
LIMIT 1000
SQL,
                    ],
                    [
                        'title' => 'Riepilogo ENASARCO totale (vista)',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT * FROM vwenasarcotot
SQL,
                    ],
                    [
                        'title' => 'Pratiche con slittamento di trimestre di competenza (OAM vs ENASARCO)',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    p.id AS id_pratica,
    p.codice_pratica,
    p.denominazione_agente,
    p.erogated_at AS data_erogazione_oam,
    CONCAT(YEAR(p.erogated_at), '-Q', QUARTER(p.erogated_at)) AS trimestre_oam,
    pr.data_fattura AS data_fattura_enasarco,
    CONCAT(YEAR(pr.data_fattura), '-Q', QUARTER(pr.data_fattura)) AS trimestre_enasarco,
    pr.importo AS provvigione_passiva_slittata,
    DATEDIFF(pr.data_fattura, p.erogated_at) AS giorni_scostamento
FROM pratiches p
INNER JOIN provvigioni pr ON pr.id_pratica = p.id
WHERE pr.entrata_uscita = 'Uscita'
  AND p.erogated_at IS NOT NULL
  AND pr.data_fattura IS NOT NULL
  AND pr.annullato = 0
  AND pr.deleted_at IS NULL
  AND (YEAR(p.erogated_at) != YEAR(pr.data_fattura)
       OR QUARTER(p.erogated_at) != QUARTER(pr.data_fattura))
ORDER BY p.erogated_at DESC
LIMIT 1000
SQL,
                    ],
                    [
                        'title' => 'Volumi slittati tra trimestri per agente',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    p.denominazione_agente,
    YEAR(p.erogated_at) AS anno_oam,
    QUARTER(p.erogated_at) AS trimestre_oam,
    YEAR(pr.data_fattura) AS anno_enasarco,
    QUARTER(pr.data_fattura) AS trimestre_enasarco,
    COUNT(DISTINCT p.id) AS pratiche_disallineate,
    SUM(pr.importo) AS totale_provvigioni_passive_slittate
FROM pratiches p
INNER JOIN provvigioni pr ON pr.id_pratica = p.id
WHERE pr.entrata_uscita = 'Uscita'
  AND p.erogated_at IS NOT NULL
  AND pr.data_fattura IS NOT NULL
  AND pr.annullato = 0
  AND pr.deleted_at IS NULL
  AND (YEAR(p.erogated_at) != YEAR(pr.data_fattura)
       OR QUARTER(p.erogated_at) != QUARTER(pr.data_fattura))
GROUP BY
    p.denominazione_agente,
    YEAR(p.erogated_at),
    QUARTER(p.erogated_at),
    YEAR(pr.data_fattura),
    QUARTER(pr.data_fattura)
ORDER BY totale_provvigioni_passive_slittate DESC
LIMIT 20
SQL,
                    ],
                ],
            ],

            // -----------------------------------------------------------------
            // Dashboard storica della company 1, riassegnata alla company 2 e
            // rifatta sul modello dati REALE di proforma: i widget su `invoices`
            // (tabella quasi vuota qui) sono stati sostituiti con equivalenti su
            // `provvigioni`. Restano le analisi su `pratiches` perfezionate.
            // -----------------------------------------------------------------
            [
                'dashboard' => 'Gestione Provvigioni & Fatturazione',
                'description' => 'Provvigioni passive da liquidare, attivo da incassare, ultime provvigioni fatturate e analisi delle pratiche perfezionate per prodotto e agente.',
                'icon' => 'heroicon-o-banknotes',
                'category' => 'Produzione',
                'company_id' => 2,
                'widgets' => [
                    [
                        'title' => 'Provvigioni passive per agente',
                        'type' => 'pie',
                        'query' => <<<'SQL'
SELECT
    denominazione_riferimento AS agente,
    COUNT(id) AS numero_provvigioni,
    SUM(importo) AS totale_passivo
FROM provvigioni
WHERE entrata_uscita = 'Uscita'
  AND annullato = 0
  AND deleted_at IS NULL
GROUP BY denominazione_riferimento
ORDER BY totale_passivo DESC
LIMIT 30
SQL,
                        'children' => [
                            [
                                'title' => 'Provvigioni passive dell\'agente per anno',
                                'type' => 'bar',
                                'filter_column' => 'totale_passivo',
                                'query' => <<<'SQL'
-- Parametro di input: :denominazione_riferimento
SELECT
    YEAR(COALESCE(data_pagamento, data_status, data_inserimento_compenso)) AS anno,
    COUNT(id) AS numero_provvigioni,
    SUM(importo) AS totale_passivo
FROM provvigioni
WHERE entrata_uscita = 'Uscita'
  AND annullato = 0
  AND deleted_at IS NULL
  AND denominazione_riferimento = :denominazione_riferimento
GROUP BY anno
ORDER BY anno
SQL,
                            ],
                        ],
                    ],
                    [
                        'title' => 'Provvigioni passive da liquidare per agente',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    denominazione_riferimento AS agente,
    COUNT(id) AS numero_provvigioni,
    SUM(importo) AS totale_da_liquidare
FROM provvigioni
WHERE stato = 'Inserito'
  AND entrata_uscita = 'Uscita'
  AND importo > 0
  AND annullato = 0
  AND deleted_at IS NULL
GROUP BY denominazione_riferimento
ORDER BY totale_da_liquidare DESC
SQL,
                    ],
                    [
                        'title' => 'Provvigioni passive da liquidare (dettaglio)',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    p.data_status AS data_stato,
    p.denominazione_riferimento AS agente,
    p.istituto_finanziario,
    p.importo,
    p.descrizione,
    p.id_pratica AS pratica,
    p.status_compenso AS stato_compenso
FROM provvigioni p
WHERE p.stato = 'Inserito'
  AND p.entrata_uscita = 'Uscita'
  AND p.importo > 0
  AND p.annullato = 0
  AND p.deleted_at IS NULL
ORDER BY p.data_status DESC, p.denominazione_riferimento
LIMIT 1000
SQL,
                    ],
                    [
                        'title' => 'Provvigioni attive da incassare',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    p.istituto_finanziario,
    p.id_pratica AS pratica,
    p.importo,
    p.status_compenso AS stato_compenso,
    p.data_fattura,
    p.data_inserimento_compenso
FROM provvigioni p
WHERE p.entrata_uscita = 'Entrata'
  AND p.stato <> 'Pagato'
  AND p.annullato = 0
  AND p.deleted_at IS NULL
ORDER BY p.data_inserimento_compenso DESC
LIMIT 1000
SQL,
                    ],
                    [
                        'title' => 'Ultime provvigioni fatturate',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    data_fattura,
    n_fattura,
    entrata_uscita,
    denominazione_riferimento AS riferimento,
    istituto_finanziario,
    importo,
    id_pratica AS pratica
FROM provvigioni
WHERE data_fattura IS NOT NULL
  AND annullato = 0
  AND deleted_at IS NULL
ORDER BY data_fattura DESC
LIMIT 30
SQL,
                    ],
                    [
                        'title' => 'Pratiche perfezionate per prodotto',
                        'type' => 'pie',
                        'query' => <<<'SQL'
SELECT tipo_prodotto, COUNT(id) AS n
FROM pratiches
WHERE stato_pratica = 'PERFEZIONATA'
GROUP BY tipo_prodotto
ORDER BY n DESC
SQL,
                        'children' => [
                            [
                                'title' => 'Pratiche perfezionate per istituto',
                                'type' => 'pie',
                                'filter_column' => 'n',
                                'query' => <<<'SQL'
-- Parametro di input: :tipo_prodotto
SELECT denominazione_banca, COUNT(id) AS n
FROM pratiches
WHERE stato_pratica = 'PERFEZIONATA'
  AND tipo_prodotto = :tipo_prodotto
GROUP BY denominazione_banca
ORDER BY n DESC
SQL,
                            ],
                            [
                                'title' => 'Pratiche perfezionate per agente',
                                'type' => 'pie',
                                'filter_column' => 'n',
                                'query' => <<<'SQL'
-- Parametro di input: :tipo_prodotto
SELECT denominazione_agente, COUNT(id) AS n
FROM pratiches
WHERE stato_pratica = 'PERFEZIONATA'
  AND tipo_prodotto = :tipo_prodotto
GROUP BY denominazione_agente
ORDER BY n DESC
SQL,
                            ],
                        ],
                    ],
                    [
                        'title' => 'Pratiche perfezionate: agente x prodotto',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT denominazione_agente, tipo_prodotto, COUNT(id) AS n, SUM(erogato) AS totale_erogato
FROM pratiches
WHERE stato_pratica = 'PERFEZIONATA'
GROUP BY denominazione_agente, tipo_prodotto
ORDER BY n DESC
SQL,
                    ],
                    [
                        'title' => 'Pratiche per stato',
                        'type' => 'pie',
                        'query' => <<<'SQL'
SELECT
    stato_pratica,
    COUNT(id) AS numero_pratiche
FROM pratiches
GROUP BY stato_pratica
ORDER BY numero_pratiche DESC
SQL,
                    ],
                    [
                        'title' => 'Pratiche rifiutate',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    p.id AS id_pratica,
    p.codice_pratica,
    p.denominazione_agente,
    p.denominazione_banca,
    p.tipo_prodotto,
    p.stato_pratica,
    p.data_inserimento_pratica
FROM pratiches p
JOIN pratiches_statos ps ON p.stato_pratica = ps.stato_pratica
WHERE ps.isrejected = 1
ORDER BY p.data_inserimento_pratica DESC
LIMIT 1000
SQL,
                    ],
                    [
                        'title' => 'Agenti in ordine alfabetico',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT name AS agente, piva, enasarco AS mandato_enasarco
FROM fornitoris
ORDER BY name ASC
SQL,
                    ],
                ],
            ],
        ];
    }
}
