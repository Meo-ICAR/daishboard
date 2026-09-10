<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Cruscotti clinici della coorte HIV (database `hassisdadmin`, company 3):
 * rischio cardiovascolare / placca carotidea, demografia & trattamenti e
 * gestione pazienti.
 *
 * Rettifica dei widget storici `company_id = 3`: type non canonici
 * (`'Pie Chart'` -> `pie`, ecc.), type semanticamente errati portati a `table`
 * (query senza colonna categoria o cross-tab multidimensionali), titoli grezzi
 * (erano il testo del prompt) ripuliti, colonna identificativa `iniziali`
 * sostituita con `pazientecode` (regola del profilo HIV), filtro obbligatorio
 * `active = 1` aggiunto dove mancante e `master_filter_column` valorizzato sui
 * widget figli di drill-down.
 *
 * Idempotenza per singola dashboard (skip se esiste già stesso
 * titolo + database + company_id). Registrato in DatabaseSeeder dopo
 * CompanySeeder (serve `companies.id = 3`).
 */
class HivDashboardSeeder extends Seeder
{
    private const DATABASE = 'hassisdadmin';

    private const COMPANY_ID = 3;

    public function run(): void
    {
        $now = now();
        $category = $this->categoryId('Clinica');

        foreach ($this->blueprint() as $order => $group) {
            $exists = DB::table('dashboards')
                ->where('title', $group['dashboard'])
                ->where('database', self::DATABASE)
                ->where('company_id', self::COMPANY_ID)
                ->exists();

            if ($exists) {
                continue;
            }

            $dashboardId = DB::table('dashboards')->insertGetId([
                'user_id' => null,
                'company_id' => self::COMPANY_ID,
                'database' => self::DATABASE,
                'menu_category_id' => $category,
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
                    'company_id' => self::COMPANY_ID,
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
                        'company_id' => self::COMPANY_ID,
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
     * @return array<int, array{dashboard: string, description: string, icon: string, widgets: array<int, array<string, mixed>>}>
     */
    private function blueprint(): array
    {
        return [
            [
                'dashboard' => 'Rischio Cardiovascolare & Placca Carotidea',
                'description' => 'Placca carotidea, spessore intima-media, profilo lipidico e aderenza alla terapia con statine, stratificati per nadir dei CD4, rapporto CD4/CD8 e classe di terapia ARV.',
                'icon' => 'heroicon-o-heart',
                'widgets' => [
                    [
                        'title' => 'Pazienti con angina',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    p.pazientecode,
    p.centro,
    DATE_FORMAT(p.datanascita, '%Y-%m') AS nascita,
    CONCAT('_', CONVERT(TIMESTAMPDIFF(YEAR, p.datanascita, CURDATE()), CHAR)) AS eta
FROM patients p
JOIN cardiopaties c ON p.cardiopatie_id = c.id
WHERE c.id = 'Angina'
  AND p.active = 1
SQL,
                    ],
                    [
                        'title' => 'Placca carotidea per fascia di nadir dei CD4',
                        'type' => 'bar',
                        'query' => <<<'SQL'
SELECT
    CASE
        WHEN CAST(cd4nadir AS UNSIGNED) < 200 THEN '< 200 cellule/mm³'
        WHEN CAST(cd4nadir AS UNSIGNED) BETWEEN 200 AND 349 THEN '200-349 cellule/mm³'
        WHEN CAST(cd4nadir AS UNSIGNED) BETWEEN 350 AND 499 THEN '350-499 cellule/mm³'
        WHEN CAST(cd4nadir AS UNSIGNED) >= 500 THEN '≥ 500 cellule/mm³'
        ELSE 'N/D'
    END AS fascia_cd4_nadir,
    COUNT(*) AS totale_pazienti,
    SUM(CASE WHEN PLACCA = '1' OR PLACCA = 'SI' THEN 1 ELSE 0 END) AS paz_con_placca,
    ROUND(SUM(CASE WHEN PLACCA = '1' OR PLACCA = 'SI' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) AS percentuale_placca
FROM patients
WHERE active = 1
  AND cd4nadir IS NOT NULL
  AND cd4nadir REGEXP '^[0-9]+$'
GROUP BY fascia_cd4_nadir
ORDER BY MIN(CAST(cd4nadir AS UNSIGNED))
SQL,
                        'children' => [
                            [
                                'title' => 'Sede e morfologia della placca',
                                'type' => 'table',
                                'filter_column' => 'paz_con_placca',
                                'query' => <<<'SQL'
SELECT
    SUM(CASE WHEN placca_dx = '1' OR placca_dx = 'SI' THEN 1 ELSE 0 END) AS placca_destra,
    SUM(CASE WHEN placca_sx = '1' OR placca_sx = 'SI' THEN 1 ELSE 0 END) AS placca_sinistra,
    SUM(CASE WHEN placca_bil = 1 THEN 1 ELSE 0 END) AS placca_bilaterale,
    SUM(CASE WHEN placca_f = '1' OR placca_f = 'SI' THEN 1 ELSE 0 END) AS fibrosa,
    SUM(CASE WHEN placca_c = '1' OR placca_c = 'SI' THEN 1 ELSE 0 END) AS calcifica,
    SUM(CASE WHEN placca_fc = '1' OR placca_fc = 'SI' THEN 1 ELSE 0 END) AS fibrocalcifica
FROM patients
WHERE active = 1
  AND (PLACCA = '1' OR PLACCA = 'SI')
SQL,
                            ],
                            [
                                'title' => 'Anzianità di malattia (nadir CD4 < 200)',
                                'type' => 'pie',
                                'filter_column' => 'totale_pazienti',
                                'query' => <<<'SQL'
SELECT
    CASE
        WHEN CAST(YEARS_HIV_TSA AS UNSIGNED) < 5 THEN '< 5 anni'
        WHEN CAST(YEARS_HIV_TSA AS UNSIGNED) BETWEEN 5 AND 10 THEN '5-10 anni'
        WHEN CAST(YEARS_HIV_TSA AS UNSIGNED) > 10 THEN '> 10 anni'
        ELSE 'N/D'
    END AS fascia_anzianita_hiv,
    COUNT(*) AS totale_pazienti,
    SUM(CASE WHEN PLACCA = '1' OR PLACCA = 'SI' THEN 1 ELSE 0 END) AS paz_con_placca
FROM patients
WHERE active = 1
  AND cd4nadir < 200
  AND YEARS_HIV_TSA REGEXP '^[0-9]+$'
GROUP BY fascia_anzianita_hiv
SQL,
                            ],
                        ],
                    ],
                    [
                        'title' => 'IMT e rapporto CD4/CD8 (aterosclerosi subclinica)',
                        'type' => 'line',
                        'query' => <<<'SQL'
SELECT
    CASE
        WHEN CAST(CD4_CD8_RAPP_TSA AS DECIMAL(5,2)) < 0.40 THEN '< 0.40 (Rischio Alto)'
        WHEN CAST(CD4_CD8_RAPP_TSA AS DECIMAL(5,2)) BETWEEN 0.40 AND 0.79 THEN '0.40 - 0.79'
        WHEN CAST(CD4_CD8_RAPP_TSA AS DECIMAL(5,2)) BETWEEN 0.80 AND 0.99 THEN '0.80 - 0.99'
        WHEN CAST(CD4_CD8_RAPP_TSA AS DECIMAL(5,2)) >= 1.00 THEN '≥ 1.00 (Normalizzato)'
        ELSE 'N/D'
    END AS rapporto_cd4_cd8,
    COUNT(*) AS totale_pazienti,
    ROUND(AVG(CAST(MIT_DX AS DECIMAL(5,2))), 2) AS imt_destro_medio_mm,
    ROUND(AVG(CAST(MIT_SX AS DECIMAL(5,2))), 2) AS imt_sinistro_medio_mm
FROM patients
WHERE active = 1
  AND CD4_CD8_RAPP_TSA REGEXP '^[0-9]+(\.[0-9]+)?$'
  AND MIT_DX REGEXP '^[0-9]+(\.[0-9]+)?$'
  AND MIT_SX REGEXP '^[0-9]+(\.[0-9]+)?$'
GROUP BY rapporto_cd4_cd8
ORDER BY MIN(CAST(CD4_CD8_RAPP_TSA AS DECIMAL(5,2)))
SQL,
                        'children' => [
                            [
                                'title' => 'Fattori di rischio tradizionali (CD4/CD8 < 0.40)',
                                'type' => 'table',
                                'filter_column' => 'totale_pazienti',
                                'query' => <<<'SQL'
SELECT
    COALESCE(FUMO, 'Non specificato') AS stato_fumo,
    CASE WHEN IPER_ON = 1 THEN 'Iperteso' ELSE 'Normoteso' END AS ipertensione,
    CASE WHEN diabete_id IS NOT NULL AND diabete_id != '' THEN 'Diabetico' ELSE 'Non Diabetico' END AS diabete,
    COUNT(*) AS totale_pazienti,
    ROUND(AVG(CAST(MIT_DX AS DECIMAL(5,2))), 2) AS imt_destro_medio_mm,
    ROUND(AVG(CAST(MIT_SX AS DECIMAL(5,2))), 2) AS imt_sinistro_medio_mm
FROM patients
WHERE active = 1
  AND CAST(CD4_CD8_RAPP_TSA AS DECIMAL(5,2)) < 0.40
  AND MIT_DX REGEXP '^[0-9]+(\.[0-9]+)?$'
  AND MIT_SX REGEXP '^[0-9]+(\.[0-9]+)?$'
GROUP BY stato_fumo, ipertensione, diabete
SQL,
                            ],
                            [
                                'title' => 'Stenosi carotidea (CD4/CD8 < 0.40)',
                                'type' => 'pie',
                                'filter_column' => 'totale_pazienti',
                                'query' => <<<'SQL'
SELECT
    CASE WHEN STENOsi = 1 THEN 'Con Stenosi' ELSE 'Senza Stenosi' END AS presenza_stenosi,
    COUNT(*) AS totale_pazienti,
    ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 1) AS percentuale
FROM patients
WHERE active = 1
  AND CAST(CD4_CD8_RAPP_TSA AS DECIMAL(5,2)) < 0.40
GROUP BY presenza_stenosi
SQL,
                            ],
                        ],
                    ],
                    [
                        'title' => 'Profilo lipidico e resistenza insulinica per classe di terapia ARV',
                        'type' => 'pie',
                        'query' => <<<'SQL'
SELECT
    CASE
        WHEN II_TSA = 1 AND PI_TSA = 0 THEN 'Inibitori Integrasi (INSTI)'
        WHEN PI_TSA = 1 THEN 'Inibitori Proteasi (PI)'
        WHEN NNRTI_TSA = 1 THEN 'Inibitori Non-Nucleosidici (NNRTI)'
        ELSE 'Altra Combinazione'
    END AS classe_terapia_arv,
    COUNT(*) AS totale_pazienti,
    ROUND(AVG(CAST(COLLDL_TSA AS DECIMAL(5,1))), 1) AS ldl_medio_mgdl,
    ROUND(AVG(CAST(TRIG_TSA AS DECIMAL(5,1))), 1) AS trigliceridi_medi_mgdl,
    ROUND(AVG(CAST(HOMA_TSA AS DECIMAL(5,2))), 2) AS homa_index_medio
FROM patients
WHERE active = 1
  AND COLLDL_TSA REGEXP '^[0-9]+(\.[0-9]+)?$'
  AND TRIG_TSA REGEXP '^[0-9]+(\.[0-9]+)?$'
GROUP BY classe_terapia_arv
SQL,
                        'children' => [
                            [
                                'title' => 'Dettaglio molecole INSTI (II_TSA = 1)',
                                'type' => 'pie',
                                'filter_column' => 'totale_pazienti',
                                'query' => <<<'SQL'
SELECT
    CASE
        WHEN DVG_TSA = 1 THEN 'Dolutegravir (DTG)'
        WHEN RAL_TSA = 1 THEN 'Raltegravir (RAL)'
        WHEN EVG_TSA = 1 THEN 'Elvitegravir (EVG)'
        ELSE 'Altri INSTI'
    END AS farmaco_insti,
    COUNT(*) AS totale_pazienti,
    ROUND(AVG(CAST(COLLDL_TSA AS DECIMAL(5,1))), 1) AS ldl_medio,
    ROUND(AVG(CAST(TRIG_TSA AS DECIMAL(5,1))), 1) AS trigliceridi_medi,
    ROUND(AVG(CAST(HOMA_TSA AS DECIMAL(5,2))), 2) AS homa_medio
FROM patients
WHERE active = 1
  AND II_TSA = 1
  AND COLLDL_TSA REGEXP '^[0-9]+(\.[0-9]+)?$'
GROUP BY farmaco_insti
SQL,
                            ],
                            [
                                'title' => 'Impatto delle statine concomitanti (INSTI)',
                                'type' => 'pie',
                                'filter_column' => 'totale_pazienti',
                                'query' => <<<'SQL'
SELECT
    CASE WHEN STATIN_ON = 1 THEN 'In Terapia con Statina' ELSE 'Senza Statina' END AS stato_statina,
    COUNT(*) AS totale_pazienti,
    ROUND(AVG(CAST(COLLDL_TSA AS DECIMAL(5,1))), 1) AS ldl_medio,
    ROUND(AVG(CAST(TRIG_TSA AS DECIMAL(5,1))), 1) AS trigliceridi_medi
FROM patients
WHERE active = 1
  AND II_TSA = 1
  AND COLLDL_TSA REGEXP '^[0-9]+(\.[0-9]+)?$'
GROUP BY stato_statina
SQL,
                            ],
                        ],
                    ],
                    [
                        'title' => 'Placca vs uso effettivo di statine (under-treatment)',
                        'type' => 'pie',
                        'query' => <<<'SQL'
SELECT
    CASE
        WHEN STENOsi = 1 OR PLACCA = '1' OR PLACCA = 'SI' THEN 'Danno Vascolare Presente (Placca/Stenosi)'
        ELSE 'Senza Placca/Stenosi'
    END AS condizione_vascolare,
    COUNT(*) AS totale_pazienti,
    SUM(CASE WHEN STATIN_ON = 1 THEN 1 ELSE 0 END) AS in_terapia_statina,
    SUM(CASE WHEN STATIN_ON = 0 OR STATIN_ON IS NULL THEN 1 ELSE 0 END) AS non_in_statina,
    ROUND(SUM(CASE WHEN STATIN_ON = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) AS coperti_da_statina_perc
FROM patients
WHERE active = 1
GROUP BY condizione_vascolare
SQL,
                        'children' => [
                            [
                                'title' => 'Stratificazione rischio CV (placca AND no statina)',
                                'type' => 'pie',
                                'filter_column' => 'non_in_statina',
                                'query' => <<<'SQL'
SELECT
    COALESCE(DAD_score, 'Non Calcolato') AS punteggio_dad,
    COUNT(*) AS totale_pazienti,
    ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 1) AS percentuale_sul_gap
FROM patients
WHERE active = 1
  AND (PLACCA = '1' OR PLACCA = 'SI' OR STENOsi = 1)
  AND (STATIN_ON = 0 OR STATIN_ON IS NULL)
GROUP BY punteggio_dad
SQL,
                            ],
                            [
                                'title' => 'Inerzia terapeutica per centro',
                                'type' => 'pie',
                                'filter_column' => 'non_in_statina',
                                'query' => <<<'SQL'
SELECT
    centro,
    COUNT(*) AS paz_con_placca_senza_statina,
    ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 1) AS quota_sul_totale_gap
FROM patients
WHERE active = 1
  AND (PLACCA = '1' OR PLACCA = 'SI' OR STENOsi = 1)
  AND (STATIN_ON = 0 OR STATIN_ON IS NULL)
GROUP BY centro
ORDER BY paz_con_placca_senza_statina DESC
SQL,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'dashboard' => 'Demografia Pazienti & Trattamenti',
                'description' => 'Distribuzione dei pazienti per fascia d\'età, centro e specialista; trattamenti antiretrovirali in corso.',
                'icon' => 'heroicon-o-user-group',
                'widgets' => [
                    [
                        'title' => 'Pazienti per fascia d\'età (0-18 / 19-30 / 31-50 / 51-70 / 70+)',
                        'type' => 'pie',
                        'query' => <<<'SQL'
SELECT
    CASE
        WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) <= 18 THEN '0-18'
        WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) BETWEEN 19 AND 30 THEN '19-30'
        WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) BETWEEN 31 AND 50 THEN '31-50'
        WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) BETWEEN 51 AND 70 THEN '51-70'
        WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) > 70 THEN 'Oltre 70'
        ELSE 'Sconosciuto'
    END AS fascia_eta,
    COUNT(p.id) AS numero_pazienti
FROM patients p
WHERE p.active = 1
GROUP BY fascia_eta
ORDER BY MIN(YEAR(CURDATE()) - YEAR(p.datanascita))
SQL,
                    ],
                    [
                        'title' => 'Elenco pazienti 0-18 anni',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    p.pazientecode,
    DATE_FORMAT(p.datanascita, '%Y-%m') AS nascita,
    p.centro,
    u.last_name AS specialista
FROM patients p
LEFT JOIN users u ON p.created_by = u.id
WHERE p.active = 1
  AND (YEAR(CURDATE()) - YEAR(p.datanascita)) BETWEEN 0 AND 18
SQL,
                    ],
                    [
                        'title' => 'Pazienti per fascia d\'età (0-18 / 19-30 / 31-45 / 46-60 / 61+)',
                        'type' => 'pie',
                        'query' => <<<'SQL'
SELECT
    CASE
        WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) <= 18 THEN '0-18'
        WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) BETWEEN 19 AND 30 THEN '19-30'
        WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) BETWEEN 31 AND 45 THEN '31-45'
        WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) BETWEEN 46 AND 60 THEN '46-60'
        ELSE '61 e oltre'
    END AS fascia_eta,
    COUNT(p.id) AS numero_pazienti
FROM patients p
WHERE p.active = 1
GROUP BY fascia_eta
ORDER BY MIN(YEAR(CURDATE()) - YEAR(p.datanascita))
SQL,
                    ],
                    [
                        'title' => 'Pazienti per trattamento attuale (Trattamentonuovo)',
                        'type' => 'pie',
                        'query' => <<<'SQL'
SELECT
    pv.Trattamentonuovo,
    COUNT(DISTINCT pv.patient_id) AS numero_pazienti
FROM patient_visits pv
WHERE pv.active = 1
  AND pv.Trattamentonuovo IS NOT NULL
GROUP BY pv.Trattamentonuovo
ORDER BY numero_pazienti DESC
SQL,
                    ],
                    [
                        'title' => 'Pazienti in trattamento con FTC',
                        'type' => 'bar',
                        'query' => <<<'SQL'
SELECT
    pv.Trattamentonuovo,
    COUNT(DISTINCT pv.patient_id) AS numero_pazienti
FROM patient_visits pv
WHERE pv.active = 1
  AND pv.Trattamentonuovo LIKE '%FTC%'
GROUP BY pv.Trattamentonuovo
SQL,
                    ],
                    [
                        'title' => 'Pazienti per centro',
                        'type' => 'pie',
                        'query' => <<<'SQL'
SELECT p.centro, COUNT(p.id) AS numero_pazienti
FROM patients p
WHERE p.active = 1
GROUP BY p.centro
SQL,
                    ],
                    [
                        'title' => 'Pazienti per centro e fascia d\'età',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    p.centro,
    CASE
        WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) < 30 THEN '0-29'
        WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) BETWEEN 30 AND 39 THEN '30-39'
        WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) BETWEEN 40 AND 49 THEN '40-49'
        WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) BETWEEN 50 AND 59 THEN '50-59'
        ELSE '60+'
    END AS fascia_eta,
    COUNT(p.id) AS numero_pazienti
FROM patients p
WHERE p.active = 1
GROUP BY p.centro, fascia_eta
ORDER BY p.centro, fascia_eta
SQL,
                    ],
                    [
                        'title' => 'Ultimi 5 pazienti inseriti',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT p.*
FROM patients p
WHERE p.active = 1
ORDER BY p.id DESC
LIMIT 5
SQL,
                    ],
                    [
                        'title' => 'Pazienti per specialista — anno corrente',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT * FROM vwpatientuserinsertedtots WHERE anno = YEAR(NOW())
SQL,
                    ],
                    [
                        'title' => 'Pazienti inseriti — ultimo anno',
                        'type' => 'pie',
                        'query' => <<<'SQL'
SELECT * FROM vwpatientuserinsertedsums
SQL,
                    ],
                ],
            ],
            [
                'dashboard' => 'Gestione Pazienti',
                'description' => 'Conteggi di base dei pazienti per centro, specialista e anno di visita.',
                'icon' => 'heroicon-o-clipboard-document-list',
                'widgets' => [
                    [
                        'title' => 'Pazienti attivi per centro',
                        'type' => 'bar',
                        'query' => <<<'SQL'
SELECT p.centro, COUNT(*) AS n
FROM patients p
WHERE active = 1
GROUP BY p.centro
SQL,
                    ],
                    [
                        'title' => 'Pazienti per anno di visita e centro',
                        'type' => 'pie',
                        'query' => <<<'SQL'
SELECT
    YEAR(pv.visitadel) AS anno,
    pv.centro,
    COUNT(DISTINCT pv.patient_id) AS numero_pazienti
FROM patient_visits pv
WHERE pv.active = 1
  AND pv.visitadel IS NOT NULL
GROUP BY YEAR(pv.visitadel), pv.centro
ORDER BY anno ASC, pv.centro ASC
SQL,
                    ],
                    [
                        'title' => 'Pazienti per centro',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    p.centro,
    COUNT(p.id) AS n
FROM patients AS p
WHERE p.active = 1
GROUP BY p.centro
ORDER BY n DESC
SQL,
                    ],
                    [
                        'title' => 'Pazienti per specialista',
                        'type' => 'table',
                        'query' => <<<'SQL'
SELECT
    U.center,
    CONCAT(U.last_name, ' ', U.first_name) AS specialista,
    COUNT(P.id) AS total_patients
FROM patient_visits AS P
JOIN users AS U ON P.created_by = U.id
WHERE P.active = 1
GROUP BY U.center, U.last_name, U.first_name
ORDER BY U.center, U.last_name
SQL,
                    ],
                ],
            ],
        ];
    }
}
