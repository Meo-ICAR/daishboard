<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Cruscotti del dominio "mediatore creditizio" (database `proforma`): pipeline
 * delle pratiche, analisi provvigionale e riconciliazione ENASARCO/OAM.
 *
 * Ogni query della raccolta operativa diventa un widget. Le query con parametro
 * di input (`:id_pratica`, `:denominazione_banca`, `:competenza`/`:trimestre`)
 * sono registrate come widget figli (drill-down) tramite `master_widget_id` +
 * `master_filter_column`: si aprono cliccando la cella numerica corrispondente
 * sul widget master, coerentemente con la convenzione descritta in
 * config/data_navigator.php (profilo `mediatore`, sezione 5).
 *
 * Le dashboard hanno `database = 'proforma'` per lo scoping di
 * App\Support\CompanyScope::byDatabase(). I widget "di dominio" hanno
 * `company_id` NULL (contenuto globale); la dashboard "Gestione Provvigioni &
 * Fatturazione" porta `company_id = 2` (rettifica dei widget storici della
 * company 1, riassegnati alla company 2).
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
            // Idempotenza per singola dashboard: se esiste già (stesso titolo e
            // company) non la ricrea.
            $companyId = $group['company_id'] ?? null;

            $exists = DB::table('dashboards')
                ->where('title', $group['dashboard'])
                ->where('database', self::DATABASE)
                ->when($companyId === null, fn ($q) => $q->whereNull('company_id'))
                ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
                ->exists();

            if ($exists) {
                continue;
            }

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

            foreach ($group['widgets'] as $position => $widget) {
                $masterId = DB::table('dashboard_widgets')->insertGetId([
                    'dashboard_id' => $dashboardId,
                    'company_id' => $companyId,
                    'user_id' => null,
                    'project_id' => null,
                    'master_widget_id' => null,
                    'master_filter_column' => null,
                    'title' => $widget['title'],
                    'type' => $widget['type'],
                    'query' => $widget['query'],
                    'order' => $position + 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($widget['children'] ?? [] as $childPosition => $child) {
                    DB::table('dashboard_widgets')->insert([
                        'dashboard_id' => $dashboardId,
                        'company_id' => $companyId,
                        'user_id' => null,
                        'project_id' => null,
                        'master_widget_id' => $masterId,
                        'master_filter_column' => $child['filter_column'],
                        'title' => $child['title'],
                        'type' => $child['type'],
                        'query' => $child['query'],
                        'order' => $childPosition + 1,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
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
                'description' => 'Pratiche in istruttoria, deliberate ed erogate con giorni di giacenza, code di liquidazione e tempi medi per banca e prodotto.',
                'icon' => 'heroicon-o-inbox-stack',
                'category' => 'Produzione',
                'widgets' => [
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
                'description' => 'Ricavo netto per pratica e per banca, provvigioni passive degli agenti (diretta vs coordinamento rete) e drill-down sulle singole righe provvigionali.',
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
                        'title' => 'Provvigioni passive agenti — diretta vs coordinamento rete',
                        'type' => 'bar',
                        'query' => <<<'SQL'
SELECT
    p.denominazione_agente,
    COUNT(pr.id) AS totale_compensi,
    SUM(CASE WHEN pr.coordinamento = 1 THEN pr.importo ELSE 0 END) AS compenso_coordinamento,
    SUM(CASE WHEN pr.coordinamento = 0 OR pr.coordinamento IS NULL THEN pr.importo ELSE 0 END) AS compenso_diretto,
    SUM(pr.importo) AS totale_provvigione_passiva
FROM provvigioni pr
INNER JOIN pratiches p ON pr.id_pratica = p.id
WHERE pr.entrata_uscita = 'Uscita'
  AND pr.annullato = 0
  AND pr.deleted_at IS NULL
GROUP BY p.denominazione_agente
ORDER BY totale_provvigione_passiva DESC
LIMIT 20
SQL,
                    ],
                ],
            ],
            [
                'dashboard' => 'ENASARCO & Disallineamenti OAM',
                'description' => 'Scadenziario trimestrale dei versamenti ENASARCO e pratiche il cui trimestre di competenza OAM (erogazione) non coincide con quello ENASARCO (fattura agente).',
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
WHERE competenza = 2026
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
            // Widget storici della company 1, rettificati e riassegnati alla
            // company 2. Query originariamente auto-generate: corretti i type
            // non canonici, la colonna inesistente `invoices.fornitori_id`
            // (join su `fornitore_piva` / filtro `clienti_id`), un errore di
            // sintassi e i parametri posizionali `?` -> figli di drill-down.
            // Scartati i widget su tabelle non presenti in `proforma`
            // (calls, calls_esitos, leads, proforma_provvigione,
            // vwprovv2cogedetail) e la dashboard "Report Chiamate & Lead".
            // -----------------------------------------------------------------
            [
                'dashboard' => 'Gestione Provvigioni & Fatturazione',
                'description' => 'Monitoraggio provvigioni attive/passive, incassi, pagamenti ed ENASARCO.',
                'icon' => 'heroicon-o-banknotes',
                'category' => 'Produzione',
                'company_id' => 2,
                'widgets' => [
                    [
                        'title' => 'Provvigioni per produttore',
                        'type' => 'pie',
                        'query' => <<<'SQL'
SELECT fornitore, SUM(total_amount) AS provvigioni
FROM invoices
WHERE clienti_id IS NULL
  AND fornitore IS NOT NULL
GROUP BY fornitore
SQL,
                        'children' => [
                            [
                                'title' => 'Provvigioni per competenza',
                                'type' => 'pie',
                                'filter_column' => 'provvigioni',
                                'query' => <<<'SQL'
-- Parametro di input: :fornitore
SELECT competenza, SUM(total_amount) AS provvigioni
FROM invoices
WHERE clienti_id IS NULL
  AND fornitore = :fornitore
GROUP BY competenza
ORDER BY competenza
SQL,
                            ],
                        ],
                    ],
                    [
                        'title' => 'ENASARCO — anno corrente per fornitore',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    i.fornitore,
    i.competenza,
    SUM(i.total_amount) AS fatturato,
    MAX(e.aliquota_agente) AS aliquota_agente,
    MAX(e.minimale) AS minimale,
    MAX(e.massimale) AS massimale
FROM invoices i
LEFT JOIN fornitoris f ON f.piva = i.fornitore_piva
LEFT JOIN enasarcos e ON e.competenza = i.competenza AND e.enasarco = f.enasarco
WHERE i.clienti_id IS NULL
  AND i.competenza = YEAR(CURDATE())
GROUP BY i.fornitore, i.competenza
SQL,
                    ],
                    [
                        'title' => 'Pratiche perfezionate per prodotto',
                        'type' => 'pie',
                        'query' => <<<'SQL'
SELECT tipo_prodotto, COUNT(*) AS n
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
SELECT denominazione_banca, COUNT(*) AS n
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
SELECT denominazione_agente, COUNT(*) AS n
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
SELECT denominazione_agente, tipo_prodotto, COUNT(*) AS n
FROM pratiches
WHERE stato_pratica = 'PERFEZIONATA'
GROUP BY denominazione_agente, tipo_prodotto
ORDER BY n DESC
SQL,
                    ],
                    [
                        'title' => 'Provvigioni fornitori — ultimi 3 mesi',
                        'type' => 'pie',
                        'query' => <<<'SQL'
SELECT fornitore, SUM(total_amount) AS provvigioni
FROM invoices
WHERE clienti_id IS NULL
  AND fornitore IS NOT NULL
  AND invoice_date > DATE_ADD(CURDATE(), INTERVAL -3 MONTH)
GROUP BY fornitore
SQL,
                    ],
                    [
                        'title' => 'Ultime 3 fatture',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT * FROM invoices ORDER BY invoice_date DESC LIMIT 3
SQL,
                    ],
                    [
                        'title' => 'Agenti in ordine alfabetico',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT name FROM fornitoris ORDER BY name ASC
SQL,
                    ],
                    [
                        'title' => 'Fatture ricevute per agente',
                        'type' => 'pie',
                        'query' => <<<'SQL'
SELECT
    f.name AS nome_agente,
    COUNT(i.id) AS numero_fatture,
    SUM(i.total_amount) AS importo_totale_fatture
FROM invoices i
JOIN fornitoris f ON f.piva = i.fornitore_piva
WHERE i.clienti_id IS NULL
GROUP BY f.name
ORDER BY f.name
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
    p.id,
    p.codice_pratica,
    p.nome_cliente,
    p.cognome_cliente,
    p.denominazione_agente,
    p.data_inserimento_pratica
FROM pratiches p
JOIN pratiches_statos ps ON p.stato_pratica = ps.stato_pratica
WHERE ps.isrejected = 1
SQL,
                    ],
                    [
                        'title' => 'Fatture attive da incassare',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    c.name AS istituto,
    i.invoice_date AS data_fattura,
    i.total_amount AS imponibile,
    i.tax_amount AS iva,
    (i.total_amount + i.tax_amount) AS totale_fattura,
    i.invoice_number AS numero_fattura
FROM invoices i
JOIN clientis c ON i.clienti_id = c.id
WHERE i.status <> 'paid'
  AND i.paid_at IS NULL
ORDER BY i.invoice_date ASC
SQL,
                    ],
                    [
                        'title' => 'Fatture fornitore da pagare',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    f.name AS agente,
    i.invoice_date AS data_fattura,
    i.total_amount AS imponibile,
    (i.total_amount + i.tax_amount) AS totale_fattura,
    i.invoice_number AS numero_fattura_fornitore
FROM invoices i
JOIN fornitoris f ON f.piva = i.fornitore_piva
WHERE i.paid_at IS NULL
  AND i.clienti_id IS NULL
SQL,
                    ],
                    [
                        'title' => 'Riepilogo ENASARCO',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT * FROM vwenasarcotot
SQL,
                    ],
                    [
                        'title' => 'Provvigioni passive da liquidare (dettaglio)',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    DATE_FORMAT(p.data_status, '%b') AS mese,
    p.denominazione_riferimento AS agente,
    p.data_status AS data_stato,
    p.importo,
    p.descrizione,
    p.id_pratica AS pratica,
    p.cognome,
    p.id,
    p.status_compenso AS stato
FROM provvigioni p
WHERE p.stato = 'Inserito'
  AND p.entrata_uscita = 'Uscita'
  AND p.importo > 0
ORDER BY MONTH(p.data_status) DESC, p.denominazione_riferimento
SQL,
                    ],
                    [
                        'title' => 'Ultime fatture ricevute (40 giorni)',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT fornitore, invoice_date, total_amount, invoice_number
FROM invoices
WHERE fornitore IS NOT NULL
  AND DATEDIFF(NOW(), invoice_date) < 40
ORDER BY invoice_date DESC
LIMIT 20
SQL,
                    ],
                    [
                        'title' => 'Provvigioni passive da liquidare per agente',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    p.denominazione_riferimento AS agente,
    SUM(p.importo) AS totale_da_liquidare
FROM provvigioni p
WHERE p.stato = 'Inserito'
  AND p.entrata_uscita = 'Uscita'
  AND p.importo > 0
GROUP BY p.denominazione_riferimento
ORDER BY p.denominazione_riferimento
SQL,
                    ],
                ],
            ],
        ];
    }
}
