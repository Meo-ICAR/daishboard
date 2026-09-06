<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('companies')->upsert([
            [
                'id' => 1,
                'name' => 'HASSISTO',
                'urlogo' => 'https://www.hassisto.com/wp-content/uploads/2019/09/logo_hassisto_200_80.png',
                'url_attivazione' => 'https://dbai.hassisto.com/',
                'email_admin' => 'hassistosrl@gmail.com',
                'db_secrete' => '121298798798',
                'db_connection' => 'mysql',

                'aibackground' => "You are working on a financial database containing invoice and accounting data. Business Logic Summary Agents (from the fornitoris table) submit loan applications (stored in the pratiches table). Banks (from the clientis table) pay our agency for these applications.\r\nCommissions (in the provvigioni table) are generated. This table is linked to agents via the fornitori_id field and to banks via the clienti_id field. We (the agency) calculate the commissions for our agents and send them a proforma (from the proformas table). Based on our proforma, the agents send us their invoice (an incoming/passive invoice). This invoice is stored in the invoices table and is linked to the agent using the fornitori_id field.\r\nSeparately, the banks send us their proformas. We (the agency) then issue our invoice to them (an outgoing/active invoice). This invoice is also stored in the invoices table, but it is linked to the bank using the clienti_id field.\r\nKey Data Tables: clientis: Represents the Banks (our clients). fornitoris: Represents the Agents (our suppliers/collaborators) the field name is fornitori name. provvigioni: The commissions generated from loan applications. invoices: This table holds both active (outgoing) invoices to the banks (linked by clienti_id) and passive (incoming) invoices from our agents (linked by fornitori_id). Instructions for Analysis Other tables are primarily for lookup purposes and have secondary indexes (foreign keys) linking to these main tables. Please ignore any system tables that have the word Laravel in their comments (e.g., cache, jobs, migrations).",
                'created_at' => '2025-10-22 17:20:27',
                'updated_at' => '2025-10-22 17:20:27',
                'deleted_at' => null,
            ],
            [
                'id' => 2,
                'name' => 'RACES',
                'urlogo' => 'https://races.it/wp-content/uploads/2021/05/logo_races.svg',
                'url_attivazione' => 'https://races.hassisto.com',
                'email_admin' => 'hassistosrl@gmail.com',
                'db_secrete' => '',
                'db_connection' => 'mysql',

                'aibackground' => "You are working on a financial database containing invoice and accounting data. Business Logic Summary Agents (from the fornitoris table) submit loan applications (stored in the pratiches table). Banks (from the clientis table) pay our agency for these applications.\r\nCommissions (in the provvigioni table) are generated. This table is linked to agents via the fornitori_id field and to banks via the clienti_id field. We (the agency) calculate the commissions for our agents and send them a proforma (from the proformas table). Based on our proforma, the agents send us their invoice (an incoming/passive invoice). This invoice is stored in the invoices table and is linked to the agent using the fornitori_id field.\r\nSeparately, the banks send us their proformas. We (the agency) then issue our invoice to them (an outgoing/active invoice). This invoice is also stored in the invoices table, but it is linked to the bank using the clienti_id field.\r\nKey Data Tables: clientis: Represents the Banks (our clients). fornitoris: Represents the Agents (our suppliers/collaborators) the field name is fornitori name. provvigioni: The commissions generated from loan applications. invoices: This table holds both active (outgoing) invoices to the banks (linked by clienti_id) and passive (incoming) invoices from our agents (linked by fornitori_id). Instructions for Analysis Other tables are primarily for lookup purposes and have secondary indexes (foreign keys) linking to these main tables. Please ignore any system tables that have the word Laravel in their comments (e.g., cache, jobs, migrations).",
                'created_at' => '2025-10-22 17:20:27',
                'updated_at' => '2025-10-22 17:20:27',
                'deleted_at' => null,
            ],
            [
                'id' => 3,
                'name' => 'Archiprevaleat',
                'urlogo' => 'https://www.klinksolutions.it/wp-content/uploads/2023/09/KLINK_logo-all_white.png',
                'url_attivazione' => 'https://ai.archiprevaleat.com/',
                'email_admin' => 'hassistosrl@gmail.com',
                'db_secrete' => '',
                'db_connection' => 'mysql',

                'aibackground' => "Here is the database schema and the rules you must follow:\r\nCore Tables patients (aliased as p): The main patient registry. Contains demographics (datanascita, sesso), baseline comorbidities (diabete_id, ipertensione_id), and HIV history (dataHIV, CD4nadir). patient_visits (aliased as pv): Longitudinal follow-up data. Contains visit dates (visitadel), lab results (CD4, HIVRNA, Colesterolo, HDL, LDL), and carotid IMT measurements (Carotide_comune_sx, Bulbo_dx). plaches (aliased as pl): Details on carotid plaques (like stenosi, ecogenicita_id). Linked via pl.patient_visit_id = pv.id. dopplers (aliased as d): A separate, wide table for Doppler exam results. Linked via d.patient_id = p.id. centers also named structure is field centro in both patients and patient_visits\r\n\r\nDictionary Tables (for Filtering) These tables define categories. To filter by a category, you must JOIN its dictionary table.\r\nHepatitis (epatites on p.epatite_id): Values include 'si HBV', 'si HCV', 'si HCV e HBV', 'no'. Use LIKE '%HCV%' for HCV.\r\n...and many others (e.g., cardiopaties, dislipidemies, neoplasies).\r\n\r\nCRITICAL QUERYING RULES  dopplers Table Warning: All columns in the dopplers table are varchar. For any math, comparison, or aggregation (AVG, SUM, >, <), you MUST cast the column. Correct: WHERE CAST(d.AGE_TSA AS UNSIGNED) > 50\r\n\r\nCorrect: AVG(CAST(d.CD4_TSA AS DECIMAL(10,2))) WRONG: WHERE d.AGE_TSA > 50 (This will fail or give incorrect string-based results). Lab Data Sources:  For longitudinal/visit labs (e.g., current CD4, average LDL): Use the patient_visits table (e.g., pv.CD4, pv.LDL). For baseline labs (e.g., nadir CD4, max viremia): Use the patients table (e.g., p.CD4nadir, p.HIVRNAmax). For Doppler-specific labs: Use the dopplers table (e.g., d.CD4_TSA, d.TRIG_TSA) and remember to CAST! Categorical Filters: When I ask for smokers, diabetic patients, or patients with HCV, you must JOIN the patients table with the correct dictionary table (e.g., fumos, diabetes, epatites) and filter on its id column. Example (Diabetics): ... JOIN diabetes dia ON p.diabete_id = dia.id WHERE dia.id = 'si' .  Joins: Patient to Visits: FROM patients p JOIN patient_visits pv ON p.id = pv.patient_id Visits to Plaques: ... JOIN plaches pl ON pv.id = pl.patient_visit_id Patient to Doppler: FROM patients p JOIN dopplers d ON p.id = d.patient_id Age Calculation: Calculate age from p.datanascita. Use: (YEAR(CURDATE()) - YEAR(p.datanascita))",
                'created_at' => '2025-10-22 17:20:27',
                'updated_at' => '2025-10-22 17:20:27',
                'deleted_at' => null,
            ],
        ], ['id'], [
            'name',
            'urlogo',
            'url_attivazione',
            'email_admin',
            'db_secrete',
            'db_connection',

            'aibackground',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);
    }
}
