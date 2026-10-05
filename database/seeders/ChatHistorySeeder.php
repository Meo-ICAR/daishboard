<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChatHistorySeeder extends Seeder
{
    public function run(): void
    {
        $messagesPayload = [
            [
                'role' => 'user',
                'content' => 'seleziona ultime 3 fatture',
            ],
            [
                'type' => 'tool_call',
                'tools' => [
                    [
                        'callId' => 'analyze_mysql_database_schema',
                        'name' => 'analyze_mysql_database_schema',
                        'description' => "Retrieves MySQL database schema information including tables, columns, relationships, and indexes.\n            Use this tool first to understand the database structure before writing any SQL queries.\n            Essential for generating accurate queries with proper table/column names, JOIN conditions,\n            and performance optimization. If you already know the database structure, you can skip this step.",
                        'inputs' => new \stdClass,
                        'result' => null,
                    ],
                ],
                'role' => 'assistant',
                'content' => null,
                'usage' => [
                    'input_tokens' => 854,
                    'output_tokens' => 14,
                ],
            ],
            [
                'role' => 'user',
                'content' => null,
                'type' => 'tool_call_result',
                'tools' => [
                    [
                        'callId' => 'analyze_mysql_database_schema',
                        'name' => 'analyze_mysql_database_schema',
                        'description' => "Retrieves MySQL database schema information including tables, columns, relationships, and indexes.\n            Use this tool first to understand the database structure before writing any SQL queries.\n            Essential for generating accurate queries with proper table/column names, JOIN conditions,\n            and performance optimization. If you already know the database structure, you can skip this step.",
                        'inputs' => new \stdClass,
                        'result' => "# MySQL Database Schema Analysis\n\nThis database contains 36 tables with the following structure:\n\n## Tables Overview\n- **bckprvvigioni**: 2252 rows, Primary Key: None\n- **cache**: 18 rows, Primary Key: key - Tabella cache di Laravel\n- **cache_locks**: 0 rows, Primary Key: key - Tabella lock della cache di Laravel\n- **calls**: 59315 rows, Primary Key: id - Esiti chiamate telefoniche\n- **calls_esitos**: 14 rows, Primary Key: id - esito call\n- **clientis**: 29 rows, Primary Key: id - banche\n- **companies**: 1 rows, Primary Key: id - Aziende \n- **customertypes**: 4 rows, Primary Key: id - Tipi di cliente/banca\n- **enasarcos**: 3 rows, Primary Key: id - Aliquote e massimali ENASARCO per competenza\n- **failed_jobs**: 0 rows, Primary Key: id - Tabella job falliti di Laravel\n- **fornitoris**: 41 rows, Primary Key: id - agenti\n- **invoiceins**: 1402 rows, Primary Key: id - Importazione fatture passive (staging)\n- **invoices**: 689 rows, Primary Key: id - fatture\n- **job_batches**: 0 rows, Primary Key: id - Tabella batch di job di Laravel\n- **jobs**: 0 rows, Primary Key: id - Tabella code (jobs) di Laravel\n- **leads**: 79443 rows, Primary Key: id - lead\n- **migrations**: 94 rows, Primary Key: id - Tabella migrazioni di Laravel\n- **model_has_permissions**: 0 rows, Primary Key: permission_id, model_type, model_id - Relazione permessi-modelli (Spatie)\n- **model_has_roles**: 17 rows, Primary Key: role_id, model_type, model_id - Relazione ruoli-modelli (Spatie)\n- **password_reset_tokens**: 0 rows, Primary Key: email - Tabella token reset password di Laravel\n- **permissions**: 4 rows, Primary Key: id - Tabella permessi (Spatie)\n- **personal_access_tokens**: 0 rows, Primary Key: id - Tabella token accesso personale (Sanctum/Passport)\n- **pratiches**: 3951 rows, Primary Key: id - Anagrafica pratiche finanziarie\n- **pratiches_statos**: 26 rows, Primary Key: stato_pratica - Stati possibili delle pratiche e loro flag\n- **proforma_provvigione**: 3 rows, Primary Key: id - provviggioni nella proforma\n- **proformas**: 1 rows, Primary Key: id - Testata dei proforma per agenti\n- **provvigioni**: 2263 rows, Primary Key: id\n- **provvigioni_statos**: 8 rows, Primary Key: stato\n- **role_has_permissions**: 8 rows, Primary Key: permission_id, role_id\n- **roles**: 6 rows, Primary Key: id\n- **sessions**: 108 rows, Primary Key: id\n- **status_praticas**: 16 rows, Primary Key: None\n- **tips**: 1 rows, Primary Key: id\n- **tmppagato**: 192 rows, Primary Key: None\n- **tmprovvigioni**: 534 rows, Primary Key: None\n- **users**: 15 rows, Primary Key: id",
                    ],
                ],
            ],
        ];

        DB::table('chat_history')->upsert([
            [
                'id' => 10,
                'user_id' => null,
                'thread_id' => 'IvQpUXFHCJI3Y0Rd8n7LPEpo7d4qeeBGV6umDb13',
                'messages' => json_encode($messagesPayload),
                'created_at' => null,
                'updated_at' => null,
            ],
        ], ['id'], ['user_id', 'thread_id', 'messages', 'updated_at']);
    }
}
