<?php

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DashboardWidgetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $widgets = [
            [
                'message' => 'Pazienti per centro',
                'sqlstatement' => "SELECT\r\n  p.centro,\r\n  COUNT(p.id) AS N\r\nFROM patients AS p\r\nGROUP BY\r\n  p.centro \r\nORDER BY\r\n  N DESC;",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => 'Pazienti per specialista',
                'sqlstatement' => "SELECT U.center,\r\n  concat(U.last_name,' ',  U.first_name) as Specialista,\r\n  COUNT(P.id) AS total_patients\r\nFROM patient_visits AS P\r\nJOIN users AS U\r\n  ON P.created_by = U.id\r\n\r\nGROUP BY\r\nU.center,\r\n  U.last_name,\r\n  U.first_name \r\nORDER BY U.center, U.last_name",
                'charttype' => 'Table',
            ],
            [
                'message' => 'Pazienti con angina',
                'sqlstatement' => "SELECT p.pazientecode,  p.centro, DATE_FORMAT(p.datanascita , \"%Y-%m\") as Nascita, concat('_',CONVERT(TIMESTAMPDIFF(YEAR, p.datanascita, CURDATE()),CHAR)) as Eta FROM patients p JOIN cardiopaties c ON p.cardiopatie_id = c.id WHERE c.id = 'Angina'",
                'charttype' => 'Table',
            ],
            [
                'message' => 'Ultime 10 call',
                'sqlstatement' => "SELECT id, numero_chiamato, data_inizio, durata, stato_chiamata, esito, utente FROM calls ORDER BY id DESC LIMIT 10",
                'charttype' => 'Table',
            ],
            [
                'message' => 'N. call per esito',
                'sqlstatement' => "SELECT c.esito, COUNT(c.id) AS total_calls\r\nFROM calls c\r\nGROUP BY c.esito\r\nORDER BY total_calls DESC;",
                'charttype' => 'Line Chart',
            ],
            [
                'message' => 'dimmi calls per mese',
                'sqlstatement' => "\nSELECT\n    DATE_FORMAT(data_inizio, '%Y-%m') AS month,\n    COUNT(id) AS total_calls\nFROM\n    calls\nGROUP BY\n    month\nORDER BY\n    month;\n",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => 'dividi le calls per esito',
                'sqlstatement' => "\n    SELECT \n        ce.id AS esito,\n        COUNT(c.id) AS numero_chiamate\n    FROM \n        calls AS c\n    JOIN \n        calls_esitos AS ce ON c.esito = ce.id\n    GROUP BY \n        ce.id\n    ORDER BY \n        numero_chiamate DESC;\n",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => 'Esito calls per orario',
                'sqlstatement' => "SELECT \r\n       hour(c.data_inizio), count(*) as n\r\n FROM \r\n        calls AS c\r\n    JOIN \r\n        calls_esitos AS ce ON c.esito = ce.id\r\n    WHERE ce.id = ? group by hour(c.data_inizio)",
                'charttype' => 'Bar Chart',
            ],
            [
                'message' => 'Provvigioni per produttore',
                'sqlstatement' => "select fornitore, sum(total_amount) as provvigioni from invoices where fornitori_id is not null group by fornitore",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => 'Enasarco ultimo anno',
                'sqlstatement' => "select i.fornitore, sum(i.total_amount) as fatturato , e.* from invoices i left outer join fornitoris f on i.fornitori_id = f.id\r\nleft outer join enasarcos e on e.competenza = i.competenza and f.enarsarco = e.enasarco\r\n\r\nwhere i.fornitori_id is not null and i.competenza = year(curdate()) group by i.fornitore",
                'charttype' => 'Table',
            ],
            [
                'message' => 'Provvigioni per  competenza',
                'sqlstatement' => "select competenza, sum(total_amount) from invoices where fornitore = ?  group by competenza",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => 'Pratiche perfezionate',
                'sqlstatement' => "select tipo_prodotto, count(*) as n from pratiches where  stato_pratica = 'PERFEZIONATA' GROUP BY tipo_prodotto order by count(*)",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => 'Pratiche  x agente',
                'sqlstatement' => "select tipo_prodotto, count(*) as n from pratiches where  stato_pratica = 'PERFEZIONATA' and denominazione_agente = ? GROUP BY tipo_prodotto order by count(*)",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => 'Provvigioni ultimi 3 mesi',
                'sqlstatement' => "select fornitore, sum(total_amount) as provvigioni from invoices where fornitori_id is not null \r\nand invoice_date > DATE_ADD(CURDATE(), INTERVAL -3 MONTH)\r\ngroup by fornitore",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => 'Pratiche per Istituto',
                'sqlstatement' => "select denominazione_banca , count(*) as n from pratiches where  stato_pratica = 'PERFEZIONATA' and tipo_prodotto = ?  GROUP BY denominazione_banca ",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => 'Pratiche per agente',
                'sqlstatement' => "select 	denominazione_agente , count(*) as n from pratiches where  stato_pratica = 'PERFEZIONATA' and tipo_prodotto = ?  GROUP BY 	denominazione_agente ",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => 'visualizza ultime 3 fatture',
                'sqlstatement' => "SELECT * FROM invoices ORDER BY invoice_date DESC LIMIT 3",
                'charttype' => 'Table',
            ],
            [
                'message' => 'visualizza agenti in ordine alfabetico',
                'sqlstatement' => "SELECT name FROM fornitoris ORDER BY name ASC",
                'charttype' => 'Table',
            ],
            [
                'message' => 'Fatture  ricevute per agente',
                'sqlstatement' => "SELECT\r\n        f.name AS nome_agente,\r\n        COUNT(i.id) AS numero_fatture,\r\n        SUM(i.total_amount) AS importo_totale_fatture\r\n    FROM\r\n        invoices i\r\n    JOIN\r\n        fornitoris f ON i.fornitori_id = f.id\r\n    GROUP BY\r\n        f.name\r\n    ORDER BY\r\n        f.name;",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => 'Pratiche per stato',
                'sqlstatement' => "SELECT\r\n    stato_pratica,\r\n    COUNT(id) AS Numero_Pratiche\r\nFROM pratiches\r\nGROUP BY\r\n    stato_pratica\r\nORDER BY\r\n    Numero_Pratiche DESC;",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => 'pratiche rifiutate',
                'sqlstatement' => "   SELECT\r\n    p.id,\r\n    p.codice_pratica,\r\n    p.nome_cliente,\r\n    p.cognome_cliente,\r\n    p.denominazione_agente,\r\n    p.data_inserimento_pratica\r\nFROM pratiches p\r\nJOIN pratiches_statos ps ON p.stato_pratica = ps.stato_pratica\r\nWHERE\r\n    ps.isrejected = 1;",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => 'Lead Caldi',
                'sqlstatement' => "-- 7. Lead \"caldi\" da richiamare (interessati, con appuntamento)\r\n-- Cerca i lead che hanno un esito positivo e una data di richiamo.\r\nSELECT\r\n    concat(l.cognome,' ',l.nome ) as Lead,\r\n    l.esito,\r\n    l.data_richiamo,\r\n    l.ultimo_operatore,\r\n \r\n    l.email\r\n\r\nFROM leads l\r\nJOIN calls_esitos ce ON l.esito = ce.id\r\nWHERE\r\n    ce.islead = 1 -- Flag che indica un esito positivo/interessato\r\n    AND l.data_richiamo IS NOT NULL\r\nORDER BY\r\n    l.data_richiamo ASC;",
                'charttype' => 'Table',
            ],
            [
                'message' => 'Provvigioni da incassare',
                'sqlstatement' => "SELECT\r\n    c.name AS Istituto,\r\n    i.invoice_date AS Data_Fattura,\r\n    i.total_amount AS Imponibile,\r\n    i.tax_amount AS IVA,\r\n    (i.total_amount + i.tax_amount) AS Totale_Fattura,\r\n    i.invoice_number AS Numero_Fattura\r\nFROM invoices i\r\nJOIN clientis c ON i.clienti_id = c.id\r\nWHERE\r\n    i.status != 'paid'\r\n    AND i.paid_at IS NULL\r\n    AND i.fornitori_id IS NULL -- Assicura che sia una fattura attiva (vs cliente)\r\nORDER BY\r\n    i.invoice_date ASC;",
                'charttype' => 'Table',
            ],
            [
                'message' => 'Provvigioni da pagare',
                'sqlstatement' => "SELECT\r\n    f.name AS Agente,\r\n    i.invoice_date AS Data_Fattura,\r\n    i.total_amount AS Imponibile,\r\n    (i.total_amount + i.tax_amount) AS Totale_Fattura\r\n    i.invoice_number AS Numero_Fattura_Fornitore\r\nFROM invoices i\r\nJOIN fornitoris f ON i.fornitori_id = f.id\r\nWHERE\r\n    i.paid_at IS NULL\r\n    AND i.clienti_id IS NULL -- Assicura che sia una fattura passiva (da fornitore)",
                'charttype' => 'Table',
            ],
            [
                'message' => 'Lista provvigioni da inserire in proforma',
                'sqlstatement' => "SELECT\r\n    f.name AS Agente,\r\n    p.id_pratica,\r\n    p.importo,\r\nconcat (p.cognome,' ', p.nome) as cliente,\r\np.stato,\r\n\r\n    p.id\r\nFROM provvigioni p\r\nLEFT JOIN proforma_provvigione pp ON p.id = pp.provvigione_id\r\nleft JOIN fornitoris f ON p.fornitori_id = f.id\r\nWHERE\r\n    p.status_pratica = 'PERFEZIONATA' -- Ipotizzo questo stato",
                'charttype' => 'Table',
            ],
            [
                'message' => 'Enasarco',
                'sqlstatement' => "select * from vwenasarcotot",
                'charttype' => 'Table',
            ],
            [
                'message' => 'ultime 3 visite',
                'sqlstatement' => "SELECT * FROM patient_visits ORDER BY visitadel DESC LIMIT 3",
                'charttype' => 'Table',
            ],
            [
                'message' => "raggruppare i pazienti per fasce d'età. Userò le seguenti fasce: 0-18, 19-30, 31-50, 51-70, e oltre 70 anni.",
                'sqlstatement' => "\nSELECT\n    CASE\n        WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) <= 18 THEN '0-18'\n        WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) BETWEEN 19 AND 30 THEN '19-30'\n        WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) BETWEEN 31 AND 50 THEN '31-50'\n        WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) BETWEEN 51 AND 70 THEN '51-70'\n        WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) > 70 THEN 'Oltre 70'\n        ELSE 'Sconosciuto'\n    END AS fascia_eta,\n    COUNT(p.id) AS numero_pazienti\nFROM\n    patients p\nGROUP BY\n    fascia_eta\nORDER BY\n    MIN(YEAR(CURDATE()) - YEAR(p.datanascita));\n",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => 'lista pazienti 0-18 anni',
                'sqlstatement' => "SELECT p.iniziali,  DATE_FORMAT(p.datanascita, \"%Y-%m\") as Nascita , p.centro, u.last_name as Specialista FROM patients p left outer join users u on p.created_by = u.id WHERE (YEAR(CURDATE()) - YEAR(p.datanascita)) BETWEEN 0 AND 18",
                'charttype' => 'Table',
            ],
            [
                'message' => "pazienti per fasce d'età",
                'sqlstatement' => "SELECT CASE WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) <= 18 THEN '0-18' WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) BETWEEN 19 AND 30 THEN '19-30' WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) BETWEEN 31 AND 45 THEN '31-45' WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) BETWEEN 46 AND 60 THEN '46-60' ELSE '61 e oltre' END AS FasciaEta, COUNT(p.id) AS NumeroPazienti FROM patients p GROUP BY FasciaEta ORDER BY MIN(YEAR(CURDATE()) - YEAR(p.datanascita));",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => 'dalle visite raggruppa i trattamenti attuali campo Trattamentonuovo  contando il numero di pazienti',
                'sqlstatement' => "SELECT pv.Trattamentonuovo, COUNT(DISTINCT pv.patient_id) AS numero_pazienti FROM patient_visits pv WHERE pv.Trattamentonuovo IS NOT NULL GROUP BY pv.Trattamentonuovo ORDER BY numero_pazienti DESC",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => 'Trattamento con FTCi',
                'sqlstatement' => "SELECT pv.Trattamentonuovo, COUNT(DISTINCT pv.patient_id) AS numero_pazienti FROM patient_visits pv WHERE pv.Trattamentonuovo LIKE '%FTC%' GROUP BY pv.Trattamentonuovo",
                'charttype' => 'Bar Chart',
            ],
            [
                'message' => 'raggruppa pazienti per centro',
                'sqlstatement' => "SELECT p.centro, COUNT(p.id) AS numero_pazienti FROM patients p GROUP BY p.centro",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => 'raggruppa pazienti per centro e fascie di eta',
                'sqlstatement' => "SELECT p.centro, CASE WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) < 30 THEN '0-29' WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) BETWEEN 30 AND 39 THEN '30-39' WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) BETWEEN 40 AND 49 THEN '40-49' WHEN (YEAR(CURDATE()) - YEAR(p.datanascita)) BETWEEN 50 AND 59 THEN '50-59' ELSE '60+' END AS fascia_eta, COUNT(p.id) AS numero_pazienti FROM patients p GROUP BY p.centro, fascia_eta ORDER BY p.centro, fascia_eta;",
                'charttype' => 'Table',
            ],
            [
                'message' => 'dammi  ultimi 5 pazienti',
                'sqlstatement' => "SELECT p.* FROM patients p ORDER BY p.id DESC LIMIT 5",
                'charttype' => 'Table',
            ],
            [
                'message' => 'Provvigioni da pagare',
                'sqlstatement' => "select   date_format(p.data_status,'%b') as Mese, p.denominazione_riferimento as Agente, p.data_status as status, p.importo, p.descrizione, p.id_pratica as pratica, p.cognome, p.id , p.status_compenso as stato from  provvigioni p \r\n\r\nwhere p.stato = 'Inserito'\r\nand p.entrata_uscita = 'Uscita'\r\nand p.importo > 0\r\nORDER BY  month(p.data_status) desc, p.denominazione_riferimento",
                'charttype' => 'Table',
            ],
            [
                'message' => 'show users',
                'sqlstatement' => "SELECT id, azure_id, company_id, name, email, cf, email_verified_at, password, microsoft_id, remember_token, created_at, updated_at, deleted_at FROM users",
                'charttype' => 'Table',
            ],
            [
                'message' => 'SELECT   f.name AS fornitore_name,   SUM(i.total_amount) AS total_invoiced_amount FROM invoices AS i JOIN fornitoris AS f   ON i.fornitori_id = f.id WHERE   i.competenza = 2025 GROUP BY   f.name;',
                'sqlstatement' => "SELECT   f.name AS fornitore_name,   SUM(i.total_amount) AS total_invoiced_amount FROM invoices AS i JOIN fornitoris AS f   ON i.fornitori_id = f.id WHERE   i.competenza = 2025 GROUP BY   f.name;",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => 'quante fatture sono state ricevute ad ottobre',
                'sqlstatement' => "\nSELECT \n    COUNT(id) AS numero_fatture_ricevute\nFROM \n    invoices\nWHERE \n    fornitori_id IS NOT NULL AND \n    YEAR(invoice_date) = YEAR(CURDATE()) AND \n    MONTH(invoice_date) = 10;\n",
                'charttype' => 'Table',
            ],
            [
                'message' => 'Ultime fatture ricevute',
                'sqlstatement' => "SELECT fornitore, invoice_date, total_amount, invoice_number FROM invoices WHERE fornitori_id IS NOT NULL AND -datediff( now(), invoice_date ) < 40  order by invoice_date desc limit 20",
                'charttype' => 'Table',
            ],
            [
                'message' => 'visualizza fornitori',
                'sqlstatement' => "SELECT * FROM fornitoris",
                'charttype' => 'Table',
            ],
            [
                'message' => 'show distinct fornitore_piva from invoices',
                'sqlstatement' => "SELECT DISTINCT fornitore_piva FROM invoices",
                'charttype' => 'Table',
            ],
            [
                'message' => 'visualizza users',
                'sqlstatement' => "SELECT * FROM users",
                'charttype' => 'Table',
            ],
            [
                'message' => 'Pazienti per specialista 2025',
                'sqlstatement' => "select * from vwpatientuserinsertedtots where anno = year(now())",
                'charttype' => 'Table',
            ],
            [
                'message' => 'SELECT   * FROM invoices ORDER BY   created_at DESC LIMIT 3;',
                'sqlstatement' => "SELECT   * FROM invoices ORDER BY   created_at DESC LIMIT 3;",
                'charttype' => 'Table',
            ],
            [
                'message' => 'Provvigioni da pagare per agente',
                'sqlstatement' => "select   p.denominazione_riferimento as Agente,  sum(p.importo) from  provvigioni p \r\n\r\nwhere p.stato = 'Inserito'\r\nand p.entrata_uscita = 'Uscita'\r\nand p.importo > 0\r\ngroup by  p.denominazione_riferimento\r\nORDER BY  p.denominazione_riferimento",
                'charttype' => 'Table',
            ],
            [
                'message' => 'Provvigioni maturate mese precedente',
                'sqlstatement' => "select * from vwprovv2cogedetail where entrata_uscita = 'Entrata'",
                'charttype' => 'Table',
            ],
            [
                'message' => 'Provvigioni passive mese precedente',
                'sqlstatement' => "select * from vwprovv2cogedetail where entrata_uscita = 'Uscita'",
                'charttype' => 'Table',
            ],
            [
                'message' => 'Pazienti ultimo anno',
                'sqlstatement' => "select * from vwpatientuserinsertedsums",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => '1 Placca carotidea in base Nadir dei CD4',
                'sqlstatement' => "SELECT\r\n  CASE\r\n    WHEN CAST(cd4nadir AS UNSIGNED) < 200 THEN '< 200 cellule/mm³'\r\n    WHEN CAST(cd4nadir AS UNSIGNED) BETWEEN 200 AND 349 THEN '200-349 cellule/mm³'\r\n    WHEN CAST(cd4nadir AS UNSIGNED) BETWEEN 350 AND 499 THEN '350-499 cellule/mm³'\r\n    WHEN CAST(cd4nadir AS UNSIGNED) >= 500 THEN '≥ 500 cellule/mm³'\r\n    ELSE 'N/D'\r\n  END AS fascia_cd4_nadir,\r\n  COUNT(*) AS totale_pazienti,\r\n  SUM(CASE WHEN PLACCA = '1' OR PLACCA = 'SI' THEN 1 ELSE 0 END) AS paz_con_placca,\r\n  ROUND(SUM(CASE WHEN PLACCA = '1' OR PLACCA = 'SI' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) AS percentuale_placca\r\nFROM patients\r\nWHERE active = 1 AND cd4nadir IS NOT NULL AND cd4nadir REGEXP '^[0-9]+$'\r\nGROUP BY fascia_cd4_nadir\r\nORDER BY MIN(CAST(cd4nadir AS UNSIGNED));",
                'charttype' => 'Bar Chart',
            ],
            [
                'message' => '2 Spessore Intima-Media (IMT) e Rapporto CD4/CD8 (Aterosclerosi Subclinica)',
                'sqlstatement' => "SELECT\r\n  CASE\r\n    WHEN CAST(CD4_CD8_RAPP_TSA AS DECIMAL(5,2)) < 0.40 THEN '< 0.40 (Rischio Alto)'\r\n    WHEN CAST(CD4_CD8_RAPP_TSA AS DECIMAL(5,2)) BETWEEN 0.40 AND 0.79 THEN '0.40 - 0.79'\r\n    WHEN CAST(CD4_CD8_RAPP_TSA AS DECIMAL(5,2)) BETWEEN 0.80 AND 0.99 THEN '0.80 - 0.99'\r\n    WHEN CAST(CD4_CD8_RAPP_TSA AS DECIMAL(5,2)) >= 1.00 THEN '≥ 1.00 (Normalizzato)'\r\n    ELSE 'N/D'\r\n  END AS rapporto_cd4_cd8,\r\n  COUNT(*) AS totale_pazienti,\r\n  ROUND(AVG(CAST(MIT_DX AS DECIMAL(5,2))), 2) AS imt_destro_medio_mm,\r\n  ROUND(AVG(CAST(MIT_SX AS DECIMAL(5,2))), 2) AS imt_sinistro_medio_mm\r\nFROM patients\r\nWHERE active = 1\r\n  AND CD4_CD8_RAPP_TSA REGEXP '^[0-9]+(\\.[0-9]+)?$'\r\n  AND MIT_DX REGEXP '^[0-9]+(\\.[0-9]+)?$'\r\n  AND MIT_SX REGEXP '^[0-9]+(\\.[0-9]+)?$'\r\nGROUP BY rapporto_cd4_cd8\r\nORDER BY MIN(CAST(CD4_CD8_RAPP_TSA AS DECIMAL(5,2)));",
                'charttype' => 'Line Chart',
            ],
            [
                'message' => '3 Profilo Lipidico e Resistenza Insulinica per Classe di Terapia ARV',
                'sqlstatement' => "SELECT\r\n  CASE\r\n    WHEN II_TSA = 1 AND PI_TSA = 0 THEN 'Inibitori Integrasi (INSTI)'\r\n    WHEN PI_TSA = 1 THEN 'Inibitori Proteasi (PI)'\r\n    WHEN NNRTI_TSA = 1 THEN 'Inibitori Non-Nucleosidici (NNRTI)'\r\n    ELSE 'Altra Combinazione'\r\n  END AS classe_terapia_arv,\r\n  COUNT(*) AS totale_pazienti,\r\n  ROUND(AVG(CAST(COLLDL_TSA AS DECIMAL(5,1))), 1) AS ldl_medio_mgdl,\r\n  ROUND(AVG(CAST(TRIG_TSA AS DECIMAL(5,1))), 1) AS trigliceridi_medi_mgdl,\r\n  ROUND(AVG(CAST(HOMA_TSA AS DECIMAL(5,2))), 2) AS homa_index_medio\r\nFROM patients\r\nWHERE active = 1\r\n  AND COLLDL_TSA REGEXP '^[0-9]+(\\.[0-9]+)?$'\r\n  AND TRIG_TSA REGEXP '^[0-9]+(\\.[0-9]+)?$'\r\nGROUP BY classe_terapia_arv;",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => '4 Under-treatment: Presenza di Placca vs Uso Effettivo di Statine',
                'sqlstatement' => "SELECT\r\n  CASE\r\n    WHEN STENOsi = 1 OR PLACCA = '1' OR PLACCA = 'SI' THEN 'Danno Vascolare Presente (Placca/Stenosi)'\r\n    ELSE 'Senza Placca/Stenosi'\r\n  END AS condizione_vascolare,\r\n  COUNT(*) AS totale_pazienti,\r\n  SUM(CASE WHEN STATIN_ON = 1 THEN 1 ELSE 0 END) AS in_terapia_statina,\r\n  SUM(CASE WHEN STATIN_ON = 0 OR STATIN_ON IS NULL THEN 1 ELSE 0 END) AS non_in_statina,\r\n  ROUND(SUM(CASE WHEN STATIN_ON = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) AS coperti_da_statina_perc\r\nFROM patients\r\nWHERE active = 1\r\nGROUP BY condizione_vascolare;",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => '1.1 Sede e Morfologia della Placca',
                'sqlstatement' => "SELECT\r\n  SUM(CASE WHEN placca_dx = '1' OR placca_dx = 'SI' THEN 1 ELSE 0 END) AS placca_destra,\r\n  SUM(CASE WHEN placca_sx = '1' OR placca_sx = 'SI' THEN 1 ELSE 0 END) AS placca_sinistra,\r\n  SUM(CASE WHEN placca_bil = 1 THEN 1 ELSE 0 END) AS placca_bilaterale,\r\n  SUM(CASE WHEN placca_f = '1' OR placca_f = 'SI' THEN 1 ELSE 0 END) AS fibrosa,\r\n  SUM(CASE WHEN placca_c = '1' OR placca_c = 'SI' THEN 1 ELSE 0 END) AS calcifica,\r\n  SUM(CASE WHEN placca_fc = '1' OR placca_fc = 'SI' THEN 1 ELSE 0 END) AS fibrocalcifica\r\nFROM patients\r\nWHERE active = 1  AND\r\n  (PLACCA = '1' OR PLACCA = 'SI');",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => '1.2 Anzianità di Malattia',
                'sqlstatement' => "SELECT\r\n  CASE\r\n    WHEN CAST(YEARS_HIV_TSA AS UNSIGNED) < 5 THEN '< 5 anni'\r\n    WHEN CAST(YEARS_HIV_TSA AS UNSIGNED) BETWEEN 5 AND 10 THEN '5-10 anni'\r\n    WHEN CAST(YEARS_HIV_TSA AS UNSIGNED) > 10 THEN '> 10 anni'\r\n    ELSE 'N/D'\r\n  END AS fascia_anzianita_hiv,\r\n  COUNT(*) AS totale_pazienti,\r\n  SUM(CASE WHEN PLACCA = '1' OR PLACCA = 'SI' THEN 1 ELSE 0 END) AS paz_con_placca\r\nFROM patients\r\nWHERE active = 1\r\n  AND cd4nadir < 200\r\n  AND YEARS_HIV_TSA REGEXP '^[0-9]+$'\r\nGROUP BY fascia_anzianita_hiv;",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => '2.1: Fattori di Rischio Tradizionali (Filtro: CD4/CD8 < 0.40)',
                'sqlstatement' => "SELECT\r\n  COALESCE(FUMO, 'Non specificato') AS stato_fumo,\r\n  CASE WHEN IPER_ON = 1 THEN 'Iperteso' ELSE 'Normoteso' END AS ipertensione,\r\n  CASE WHEN diabete_id IS NOT NULL AND diabete_id != '' THEN 'Diabetico' ELSE 'Non Diabetico' END AS diabete,\r\n  COUNT(*) AS totale_pazienti,\r\n  ROUND(AVG(CAST(MIT_DX AS DECIMAL(5,2))), 2) AS imt_destro_medio_mm,\r\n  ROUND(AVG(CAST(MIT_SX AS DECIMAL(5,2))), 2) AS imt_sinistro_medio_mm\r\nFROM patients\r\nWHERE active = 1\r\n  AND CAST(CD4_CD8_RAPP_TSA AS DECIMAL(5,2)) < 0.40\r\n  AND MIT_DX REGEXP '^[0-9]+(\\.[0-9]+)?$'\r\n  AND MIT_SX REGEXP '^[0-9]+(\\.[0-9]+)?$'\r\nGROUP BY stato_fumo, ipertensione, diabete;",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => '2.2 Tasso di Stenosi Carotidea (Filtro: CD4/CD8 < 0.40)',
                'sqlstatement' => "SELECT\r\n  CASE WHEN STENOsi = 1 THEN 'Con Stenosi' ELSE 'Senza Stenosi' END AS presenza_stenosi,\r\n  COUNT(*) AS totale_pazienti,\r\n  ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 1) AS percentuale\r\nFROM patients\r\nWHERE active = 1\r\n  AND CAST(CD4_CD8_RAPP_TSA AS DECIMAL(5,2)) < 0.40\r\nGROUP BY presenza_stenosi;",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => '3.1 Dettaglio Singole Molecole (Filtro: Inibitori Integrasi II_TSA = 1)',
                'sqlstatement' => "SELECT\r\n  CASE\r\n    WHEN DVG_TSA = 1 THEN 'Dolutegravir (DTG)'\r\n    WHEN RAL_TSA = 1 THEN 'Raltegravir (RAL)'\r\n    WHEN EVG_TSA = 1 THEN 'Elvitegravir (EVG)'\r\n    ELSE 'Altri INSTI'\r\n  END AS farmaco_insti,\r\n  COUNT(*) AS totale_pazienti,\r\n  ROUND(AVG(CAST(COLLDL_TSA AS DECIMAL(5,1))), 1) AS ldl_medio,\r\n  ROUND(AVG(CAST(TRIG_TSA AS DECIMAL(5,1))), 1) AS trigliceridi_medi,\r\n  ROUND(AVG(CAST(HOMA_TSA AS DECIMAL(5,2))), 2) AS homa_medio\r\nFROM patients\r\nWHERE active = 1\r\n  AND II_TSA = 1\r\n  AND COLLDL_TSA REGEXP '^[0-9]+(\\.[0-9]+)?$'\r\nGROUP BY farmaco_insti;",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => '3.2 Impatto del Trattamento Concomitante con Statine',
                'sqlstatement' => "SELECT\r\n  CASE WHEN STATIN_ON = 1 THEN 'In Terapia con Statina' ELSE 'Senza Statina' END AS stato_statina,\r\n  COUNT(*) AS totale_pazienti,\r\n  ROUND(AVG(CAST(COLLDL_TSA AS DECIMAL(5,1))), 1) AS ldl_medio,\r\n  ROUND(AVG(CAST(TRIG_TSA AS DECIMAL(5,1))), 1) AS trigliceridi_medi\r\nFROM patients\r\nWHERE active = 1\r\n  AND II_TSA = 1\r\n  AND COLLDL_TSA REGEXP '^[0-9]+(\\.[0-9]+)?$'\r\nGROUP BY stato_statina;",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => '4.1 Stratificazione del Rischio Cardiovascolare (Filtro: Placca Presente AND No Statina)',
                'sqlstatement' => "SELECT\r\n  COALESCE(DAD_score, 'Non Calcolato') AS punteggio_dad,\r\n  COUNT(*) AS totale_pazienti,\r\n  ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 1) AS percentuale_sul_gap\r\nFROM patients\r\nWHERE active = 1\r\n  AND (PLACCA = '1' OR PLACCA = 'SI' OR STENOsi = 1)\r\n  AND (STATIN_ON = 0 OR STATIN_ON IS NULL)\r\nGROUP BY punteggio_dad;",
                'charttype' => 'Pie Chart',
            ],
            [
                'message' => '4.2 Inerzia Terapeutica Mappata per Centro',
                'sqlstatement' => "SELECT\r\n  centro,\r\n  COUNT(*) AS paz_con_placca_senza_statina,\r\n  ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 1) AS quota_sul_totale_gap\r\nFROM patients\r\nWHERE active = 1\r\n  AND (PLACCA = '1' OR PLACCA = 'SI' OR STENOsi = 1)\r\n  AND (STATIN_ON = 0 OR STATIN_ON IS NULL)\r\nGROUP BY centro\r\nORDER BY paz_con_placca_senza_statina DESC;",
                'charttype' => 'Pie Chart',
            ],
        ];

        $now = now();

        foreach ($widgets as $index => $widget) {
            DB::table('dashboard_widgets')->insert([
                'message'         => $widget['message'],
                'sqlstatement'    => $widget['sqlstatement'],
                'charttype'       => $widget['charttype'],
                'dashboardorder'  => $index + 1,
                'slavedashboard'  => 0,
                'nviewed'         => 0,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }
    }
}