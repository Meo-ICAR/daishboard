<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Schema completo del database `daishboard`, rigenerato dal server MySQL.
 *
 * Idempotente: crea solo le tabelle, colonne, indici e chiavi esterne che mancano,
 * quindi si puo' rieseguire su un database gia' popolato senza errori e senza toccare i dati.
 * Le chiavi esterne che i dati storici non rispettano vengono saltate e segnalate nel log.
 */
return new class extends Migration
{
    public function getConnection()
    {
        return null;
    }

    public function up(): void
    {
        $db = DB::connection($this->getConnection());
        $schema = $db->getDatabaseName();
        $db->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($this->tables() as $table => $def) {
                $exists = $db->selectOne('select 1 as x from information_schema.tables where table_schema = ? and table_name = ?', [$schema, $table]);
                if (! $exists) {
                    $db->statement($def['create']);

                    continue;
                }
                foreach ($def['columns'] as $column => $sql) {
                    $has = $db->selectOne('select 1 as x from information_schema.columns where table_schema = ? and table_name = ? and column_name = ?', [$schema, $table, $column]);
                    if (! $has) {
                        if (stripos($sql, 'AUTO_INCREMENT') !== false && ! $db->selectOne("select 1 as x from information_schema.statistics where table_schema = ? and table_name = ? and index_name = 'PRIMARY'", [$schema, $table])) {
                            $sql .= ", ADD PRIMARY KEY (`{$column}`)";
                        }
                        $db->statement("ALTER TABLE `{$table}` ADD COLUMN {$sql}");
                    }
                }
                foreach ($def['indexes'] as $index => $sql) {
                    $has = $db->selectOne('select 1 as x from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ?', [$schema, $table, $index]);
                    if (! $has) {
                        $this->tryStatement($db, "ALTER TABLE `{$table}` ADD {$sql}", "{$table}.{$index}");
                    }
                }
            }

            foreach ($this->tables() as $table => $def) {
                foreach ($def['foreign'] as $name => $sql) {
                    $has = $db->selectOne("select 1 as x from information_schema.table_constraints where table_schema = ? and table_name = ? and constraint_name = ? and constraint_type = 'FOREIGN KEY'", [$schema, $table, $name]);
                    if (! $has) {
                        $this->tryStatement($db, "ALTER TABLE `{$table}` ADD {$sql}", "{$table}.{$name}");
                    }
                }
            }
        } finally {
            $db->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(): void
    {
        // Non si elimina nulla: lo schema e i dati sono condivisi.
    }

    private function tryStatement($db, string $sql, string $label): void
    {
        try {
            $db->statement($sql);
        } catch (\Throwable $e) {
            Log::warning("Migration daishboard: saltato {$label}: ".$e->getMessage());
        }
    }

    /** @return array<string, array{create: string, columns: array<string, string>, indexes: array<string, string>, foreign: array<string, string>}> */
    private function tables(): array
    {
        return [
        'cache' => [
            'create' => 'CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'key' => '`key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'value' => '`value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `key`',
                'expiration' => '`expiration` bigint NOT NULL AFTER `value`',
            ],
            'indexes' => [
                'cache_expiration_index' => 'KEY `cache_expiration_index` (`expiration`)',
            ],
            'foreign' => [
            ],
        ],
        'cache_locks' => [
            'create' => 'CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'key' => '`key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'owner' => '`owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `key`',
                'expiration' => '`expiration` bigint NOT NULL AFTER `owner`',
            ],
            'indexes' => [
                'cache_locks_expiration_index' => 'KEY `cache_locks_expiration_index` (`expiration`)',
            ],
            'foreign' => [
            ],
        ],
        'chat_histories' => [
            'create' => 'CREATE TABLE `chat_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco del record di cronologia\',
  `user_id` bigint unsigned DEFAULT NULL,
  `thread_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Identificativo univoco del thread/sessione\',
  `messages` json DEFAULT NULL COMMENT \'Contenuto strutturato dei messaggi e tool call in formato JSON\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chat_histories_thread_id_unique` (`thread_id`),
  KEY `chat_histories_user_id_foreign` (`user_id`),
  KEY `chat_histories_thread_id_index` (`thread_id`),
  KEY `company_id` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Storico delle conversazioni e query analizzate dall\'\'AI\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco del record di cronologia\' FIRST',
                'user_id' => '`user_id` bigint unsigned DEFAULT NULL AFTER `id`',
                'thread_id' => '`thread_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Identificativo univoco del thread/sessione\' AFTER `user_id`',
                'messages' => '`messages` json DEFAULT NULL COMMENT \'Contenuto strutturato dei messaggi e tool call in formato JSON\' AFTER `thread_id`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `messages`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
                'company_id' => '`company_id` bigint unsigned DEFAULT NULL AFTER `updated_at`',
            ],
            'indexes' => [
                'chat_histories_thread_id_unique' => 'UNIQUE KEY `chat_histories_thread_id_unique` (`thread_id`)',
                'chat_histories_user_id_foreign' => 'KEY `chat_histories_user_id_foreign` (`user_id`)',
                'chat_histories_thread_id_index' => 'KEY `chat_histories_thread_id_index` (`thread_id`)',
                'company_id' => 'KEY `company_id` (`company_id`)',
            ],
            'foreign' => [
                'chat_histories_ibfk_1' => 'CONSTRAINT `chat_histories_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)',
                'chat_histories_user_id_foreign' => 'CONSTRAINT `chat_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL',
            ],
        ],
        'chat_messages' => [
            'create' => 'CREATE TABLE `chat_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `thread_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` json DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chat_messages_thread_id_id_index` (`thread_id`,`id`),
  KEY `chat_messages_thread_id_index` (`thread_id`),
  KEY `chat_messages_company_id_foreign` (`company_id`),
  KEY `chat_messages_user_id_foreign` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Messaggi delle conversazioni con l\'\'assistente AI (NeuronAI)\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'company_id' => '`company_id` bigint unsigned DEFAULT NULL AFTER `id`',
                'user_id' => '`user_id` bigint unsigned DEFAULT NULL AFTER `company_id`',
                'thread_id' => '`thread_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `user_id`',
                'role' => '`role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `thread_id`',
                'content' => '`content` json DEFAULT NULL AFTER `role`',
                'meta' => '`meta` json DEFAULT NULL AFTER `content`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `meta`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'chat_messages_thread_id_id_index' => 'KEY `chat_messages_thread_id_id_index` (`thread_id`,`id`)',
                'chat_messages_thread_id_index' => 'KEY `chat_messages_thread_id_index` (`thread_id`)',
                'chat_messages_company_id_foreign' => 'KEY `chat_messages_company_id_foreign` (`company_id`)',
                'chat_messages_user_id_foreign' => 'KEY `chat_messages_user_id_foreign` (`user_id`)',
            ],
            'foreign' => [
                'chat_messages_company_id_foreign' => 'CONSTRAINT `chat_messages_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL',
                'chat_messages_user_id_foreign' => 'CONSTRAINT `chat_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL',
            ],
        ],
        'companies' => [
            'create' => 'CREATE TABLE `companies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `urlogo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_attivazione` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_admin` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `db_secrete` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `db_connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'mysql\',
  `db_host` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `db_port` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'3306\',
  `db_database` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `database` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `db_username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `db_password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aibackground` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'urlogo' => '`urlogo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `name`',
                'url_attivazione' => '`url_attivazione` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `urlogo`',
                'email_admin' => '`email_admin` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `url_attivazione`',
                'db_secrete' => '`db_secrete` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `email_admin`',
                'db_connection' => '`db_connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'mysql\' AFTER `db_secrete`',
                'db_host' => '`db_host` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `db_connection`',
                'db_port' => '`db_port` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'3306\' AFTER `db_host`',
                'db_database' => '`db_database` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `db_port`',
                'database' => '`database` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `db_database`',
                'db_username' => '`db_username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `database`',
                'db_password' => '`db_password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `db_username`',
                'aibackground' => '`aibackground` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `db_password`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `aibackground`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
                'deleted_at' => '`deleted_at` timestamp NULL DEFAULT NULL AFTER `updated_at`',
            ],
            'indexes' => [
            ],
            'foreign' => [
            ],
        ],
        'dashboard_widget_shares' => [
            'create' => 'CREATE TABLE `dashboard_widget_shares` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Slug pubblico non indovinabile del link\',
  `dashboard_widget_id` bigint unsigned NOT NULL,
  `project_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Titolo mostrato nella pagina pubblica\',
  `parameters` json NOT NULL COMMENT \'Parametri memorizzati: dateFilters, ecc.\',
  `include_children` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Consente di aprire le tabelle figlio dal link\',
  `expires_at` timestamp NULL DEFAULT NULL COMMENT \'Scadenza del link, null = nessuna\',
  `last_viewed_at` timestamp NULL DEFAULT NULL,
  `views` int unsigned NOT NULL DEFAULT \'0\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dashboard_widget_shares_token_unique` (`token`),
  KEY `dashboard_widget_shares_dashboard_widget_id_foreign` (`dashboard_widget_id`),
  KEY `dashboard_widget_shares_created_by_foreign` (`created_by`),
  KEY `dashboard_widget_shares_project_id_foreign` (`project_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Link pubblici per condividere una tabella widget con parametri memorizzati\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'token' => '`token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Slug pubblico non indovinabile del link\' AFTER `id`',
                'dashboard_widget_id' => '`dashboard_widget_id` bigint unsigned NOT NULL AFTER `token`',
                'project_id' => '`project_id` bigint unsigned DEFAULT NULL AFTER `dashboard_widget_id`',
                'created_by' => '`created_by` bigint unsigned DEFAULT NULL AFTER `project_id`',
                'title' => '`title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Titolo mostrato nella pagina pubblica\' AFTER `created_by`',
                'parameters' => '`parameters` json NOT NULL COMMENT \'Parametri memorizzati: dateFilters, ecc.\' AFTER `title`',
                'include_children' => '`include_children` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Consente di aprire le tabelle figlio dal link\' AFTER `parameters`',
                'expires_at' => '`expires_at` timestamp NULL DEFAULT NULL COMMENT \'Scadenza del link, null = nessuna\' AFTER `include_children`',
                'last_viewed_at' => '`last_viewed_at` timestamp NULL DEFAULT NULL AFTER `expires_at`',
                'views' => '`views` int unsigned NOT NULL DEFAULT \'0\' AFTER `last_viewed_at`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `views`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'dashboard_widget_shares_token_unique' => 'UNIQUE KEY `dashboard_widget_shares_token_unique` (`token`)',
                'dashboard_widget_shares_dashboard_widget_id_foreign' => 'KEY `dashboard_widget_shares_dashboard_widget_id_foreign` (`dashboard_widget_id`)',
                'dashboard_widget_shares_created_by_foreign' => 'KEY `dashboard_widget_shares_created_by_foreign` (`created_by`)',
                'dashboard_widget_shares_project_id_foreign' => 'KEY `dashboard_widget_shares_project_id_foreign` (`project_id`)',
            ],
            'foreign' => [
                'dashboard_widget_shares_created_by_foreign' => 'CONSTRAINT `dashboard_widget_shares_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL',
                'dashboard_widget_shares_dashboard_widget_id_foreign' => 'CONSTRAINT `dashboard_widget_shares_dashboard_widget_id_foreign` FOREIGN KEY (`dashboard_widget_id`) REFERENCES `dashboard_widgets` (`id`) ON DELETE CASCADE',
                'dashboard_widget_shares_project_id_foreign' => 'CONSTRAINT `dashboard_widget_shares_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL',
            ],
        ],
        'dashboard_widgets' => [
            'create' => 'CREATE TABLE `dashboard_widgets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco del widget\',
  `dashboard_id` bigint unsigned NOT NULL,
  `company_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `project_id` bigint unsigned DEFAULT NULL,
  `chat_history_id` bigint unsigned DEFAULT NULL,
  `master_widget_id` bigint unsigned DEFAULT NULL,
  `master_filter_column` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Colonna della query del widget master su cui filtrare (es. n_pazienti, totale_centri)\',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Titolo del widget\',
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Tipo di componente (es. table, bar_chart, line_chart, kpi_card)\',
  `query` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Query SQL o definizione della vista generata\',
  `settings` json DEFAULT NULL COMMENT \'Mappatura campi, assi X/Y, colori e filtri del grafico\',
  `grid_position` json DEFAULT NULL COMMENT \'Posizionamento e dimensioni della griglia (x, y, width, height)\',
  `order` int NOT NULL DEFAULT \'0\' COMMENT \'Ordine di resa all\'\'interno della dashboard\',
  `is_active` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Stato di visibilità del widget\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dashboard_widgets_dashboard_id_foreign` (`dashboard_id`),
  KEY `dashboard_widgets_chat_history_id_foreign` (`chat_history_id`),
  KEY `dashboard_widgets_master_widget_id_foreign` (`master_widget_id`),
  KEY `dashboard_widgets_company_id_foreign` (`company_id`),
  KEY `dashboard_widgets_user_id_foreign` (`user_id`),
  KEY `dashboard_widgets_project_id_foreign` (`project_id`)
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Widget grafici e tabelle generati dalle righe dello storico chat\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco del widget\' FIRST',
                'dashboard_id' => '`dashboard_id` bigint unsigned NOT NULL AFTER `id`',
                'company_id' => '`company_id` bigint unsigned DEFAULT NULL AFTER `dashboard_id`',
                'user_id' => '`user_id` bigint unsigned DEFAULT NULL AFTER `company_id`',
                'project_id' => '`project_id` bigint unsigned DEFAULT NULL AFTER `user_id`',
                'chat_history_id' => '`chat_history_id` bigint unsigned DEFAULT NULL AFTER `project_id`',
                'master_widget_id' => '`master_widget_id` bigint unsigned DEFAULT NULL AFTER `chat_history_id`',
                'master_filter_column' => '`master_filter_column` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Colonna della query del widget master su cui filtrare (es. n_pazienti, totale_centri)\' AFTER `master_widget_id`',
                'title' => '`title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Titolo del widget\' AFTER `master_filter_column`',
                'type' => '`type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Tipo di componente (es. table, bar_chart, line_chart, kpi_card)\' AFTER `title`',
                'query' => '`query` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Query SQL o definizione della vista generata\' AFTER `type`',
                'settings' => '`settings` json DEFAULT NULL COMMENT \'Mappatura campi, assi X/Y, colori e filtri del grafico\' AFTER `query`',
                'grid_position' => '`grid_position` json DEFAULT NULL COMMENT \'Posizionamento e dimensioni della griglia (x, y, width, height)\' AFTER `settings`',
                'order' => '`order` int NOT NULL DEFAULT \'0\' COMMENT \'Ordine di resa all\'\'interno della dashboard\' AFTER `grid_position`',
                'is_active' => '`is_active` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Stato di visibilità del widget\' AFTER `order`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `is_active`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'dashboard_widgets_dashboard_id_foreign' => 'KEY `dashboard_widgets_dashboard_id_foreign` (`dashboard_id`)',
                'dashboard_widgets_chat_history_id_foreign' => 'KEY `dashboard_widgets_chat_history_id_foreign` (`chat_history_id`)',
                'dashboard_widgets_master_widget_id_foreign' => 'KEY `dashboard_widgets_master_widget_id_foreign` (`master_widget_id`)',
                'dashboard_widgets_company_id_foreign' => 'KEY `dashboard_widgets_company_id_foreign` (`company_id`)',
                'dashboard_widgets_user_id_foreign' => 'KEY `dashboard_widgets_user_id_foreign` (`user_id`)',
                'dashboard_widgets_project_id_foreign' => 'KEY `dashboard_widgets_project_id_foreign` (`project_id`)',
            ],
            'foreign' => [
                'dashboard_widgets_chat_history_id_foreign' => 'CONSTRAINT `dashboard_widgets_chat_history_id_foreign` FOREIGN KEY (`chat_history_id`) REFERENCES `chat_histories` (`id`) ON DELETE SET NULL',
                'dashboard_widgets_company_id_foreign' => 'CONSTRAINT `dashboard_widgets_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL',
                'dashboard_widgets_dashboard_id_foreign' => 'CONSTRAINT `dashboard_widgets_dashboard_id_foreign` FOREIGN KEY (`dashboard_id`) REFERENCES `dashboards` (`id`) ON DELETE CASCADE',
                'dashboard_widgets_master_widget_id_foreign' => 'CONSTRAINT `dashboard_widgets_master_widget_id_foreign` FOREIGN KEY (`master_widget_id`) REFERENCES `dashboard_widgets` (`id`) ON DELETE SET NULL',
                'dashboard_widgets_project_id_foreign' => 'CONSTRAINT `dashboard_widgets_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL',
                'dashboard_widgets_user_id_foreign' => 'CONSTRAINT `dashboard_widgets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL',
            ],
        ],
        'dashboards' => [
            'create' => 'CREATE TABLE `dashboards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco della dashboard\',
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT NULL,
  `database` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `menu_category_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Titolo della dashboard\',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Descrizione dettagliata dello scopo della dashboard\',
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Icona rappresentativa\',
  `order` int NOT NULL DEFAULT \'0\' COMMENT \'Ordinamento nel menu\',
  `is_active` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Indica se la dashboard è visibile\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dashboards_user_id_foreign` (`user_id`),
  KEY `dashboards_menu_category_id_foreign` (`menu_category_id`),
  KEY `dashboards_company_id_foreign` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Contenitore principale per le dashboard personalizzate\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID univoco della dashboard\' FIRST',
                'user_id' => '`user_id` bigint unsigned DEFAULT NULL AFTER `id`',
                'company_id' => '`company_id` bigint unsigned DEFAULT NULL AFTER `user_id`',
                'database' => '`database` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `company_id`',
                'menu_category_id' => '`menu_category_id` bigint unsigned DEFAULT NULL AFTER `database`',
                'title' => '`title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Titolo della dashboard\' AFTER `menu_category_id`',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `title`',
                'description' => '`description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Descrizione dettagliata dello scopo della dashboard\' AFTER `name`',
                'icon' => '`icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Icona rappresentativa\' AFTER `description`',
                'order' => '`order` int NOT NULL DEFAULT \'0\' COMMENT \'Ordinamento nel menu\' AFTER `icon`',
                'is_active' => '`is_active` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Indica se la dashboard è visibile\' AFTER `order`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `is_active`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'dashboards_user_id_foreign' => 'KEY `dashboards_user_id_foreign` (`user_id`)',
                'dashboards_menu_category_id_foreign' => 'KEY `dashboards_menu_category_id_foreign` (`menu_category_id`)',
                'dashboards_company_id_foreign' => 'KEY `dashboards_company_id_foreign` (`company_id`)',
            ],
            'foreign' => [
                'dashboards_company_id_foreign' => 'CONSTRAINT `dashboards_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL',
                'dashboards_menu_category_id_foreign' => 'CONSTRAINT `dashboards_menu_category_id_foreign` FOREIGN KEY (`menu_category_id`) REFERENCES `menu_categories` (`id`) ON DELETE SET NULL',
                'dashboards_user_id_foreign' => 'CONSTRAINT `dashboards_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL',
            ],
        ],
        'failed_jobs' => [
            'create' => 'CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'uuid' => '`uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'connection' => '`connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `uuid`',
                'queue' => '`queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `connection`',
                'payload' => '`payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `queue`',
                'exception' => '`exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `payload`',
                'failed_at' => '`failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `exception`',
            ],
            'indexes' => [
                'failed_jobs_uuid_unique' => 'UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)',
                'failed_jobs_connection_queue_failed_at_index' => 'KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)',
            ],
            'foreign' => [
            ],
        ],
        'job_batches' => [
            'create' => 'CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'total_jobs' => '`total_jobs` int NOT NULL AFTER `name`',
                'pending_jobs' => '`pending_jobs` int NOT NULL AFTER `total_jobs`',
                'failed_jobs' => '`failed_jobs` int NOT NULL AFTER `pending_jobs`',
                'failed_job_ids' => '`failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `failed_jobs`',
                'options' => '`options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `failed_job_ids`',
                'cancelled_at' => '`cancelled_at` int DEFAULT NULL AFTER `options`',
                'created_at' => '`created_at` int NOT NULL AFTER `cancelled_at`',
                'finished_at' => '`finished_at` int DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
            ],
            'foreign' => [
            ],
        ],
        'jobs' => [
            'create' => 'CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'queue' => '`queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'payload' => '`payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `queue`',
                'attempts' => '`attempts` smallint unsigned NOT NULL AFTER `payload`',
                'reserved_at' => '`reserved_at` int unsigned DEFAULT NULL AFTER `attempts`',
                'available_at' => '`available_at` int unsigned NOT NULL AFTER `reserved_at`',
                'created_at' => '`created_at` int unsigned NOT NULL AFTER `available_at`',
            ],
            'indexes' => [
                'jobs_queue_index' => 'KEY `jobs_queue_index` (`queue`)',
            ],
            'foreign' => [
            ],
        ],
        'lookup_table_column' => [
            'create' => 'CREATE TABLE `lookup_table_column` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lookup_table_id` bigint unsigned NOT NULL,
  `schema_legend_column_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lookup_column_unique` (`lookup_table_id`,`schema_legend_column_id`),
  KEY `lookup_table_column_schema_legend_column_id_foreign` (`schema_legend_column_id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Collegamento lookup <-> campo di patients/patient_visits\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'lookup_table_id' => '`lookup_table_id` bigint unsigned NOT NULL AFTER `id`',
                'schema_legend_column_id' => '`schema_legend_column_id` bigint unsigned NOT NULL AFTER `lookup_table_id`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `schema_legend_column_id`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'lookup_column_unique' => 'UNIQUE KEY `lookup_column_unique` (`lookup_table_id`,`schema_legend_column_id`)',
                'lookup_table_column_schema_legend_column_id_foreign' => 'KEY `lookup_table_column_schema_legend_column_id_foreign` (`schema_legend_column_id`)',
            ],
            'foreign' => [
                'lookup_table_column_lookup_table_id_foreign' => 'CONSTRAINT `lookup_table_column_lookup_table_id_foreign` FOREIGN KEY (`lookup_table_id`) REFERENCES `lookup_tables` (`id`) ON DELETE CASCADE',
                'lookup_table_column_schema_legend_column_id_foreign' => 'CONSTRAINT `lookup_table_column_schema_legend_column_id_foreign` FOREIGN KEY (`schema_legend_column_id`) REFERENCES `schema_legend_columns` (`id`) ON DELETE CASCADE',
            ],
        ],
        'lookup_tables' => [
            'create' => 'CREATE TABLE `lookup_tables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'dbai\',
  `table_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `key_column` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'id\',
  `label_column` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `row_count` int unsigned NOT NULL DEFAULT \'0\',
  `is_dictionary` tinyint(1) DEFAULT \'1\' COMMENT \'True se piccola enumerazione con valori elencati\',
  `is_data_table` tinyint(1) DEFAULT NULL,
  `values` json DEFAULT NULL COMMENT \'Valori: [{value, label}]\',
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lookup_tables_connection_table_name_unique` (`connection`,`table_name`)
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Tabelle di lookup/dizionario del database (dbai), collegabili ai campi della legenda\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'connection' => '`connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'dbai\' AFTER `id`',
                'table_name' => '`table_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `connection`',
                'label' => '`label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `table_name`',
                'description' => '`description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `label`',
                'key_column' => '`key_column` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'id\' AFTER `description`',
                'label_column' => '`label_column` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `key_column`',
                'row_count' => '`row_count` int unsigned NOT NULL DEFAULT \'0\' AFTER `label_column`',
                'is_dictionary' => '`is_dictionary` tinyint(1) DEFAULT \'1\' COMMENT \'True se piccola enumerazione con valori elencati\' AFTER `row_count`',
                'is_data_table' => '`is_data_table` tinyint(1) DEFAULT NULL AFTER `is_dictionary`',
                'values' => '`values` json DEFAULT NULL COMMENT \'Valori: [{value, label}]\' AFTER `is_data_table`',
                'synced_at' => '`synced_at` timestamp NULL DEFAULT NULL AFTER `values`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `synced_at`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'lookup_tables_connection_table_name_unique' => 'UNIQUE KEY `lookup_tables_connection_table_name_unique` (`connection`,`table_name`)',
            ],
            'foreign' => [
            ],
        ],
        'menu_categories' => [
            'create' => 'CREATE TABLE `menu_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'Identificativo univoco della categoria\',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome della categoria\',
  `database` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Descrizione breve della categoria\',
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Icona associata al menu (es. lucide-icon o SVG)\',
  `order` int NOT NULL DEFAULT \'0\' COMMENT \'Ordine di visualizzazione nel menu\',
  `is_active` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Stato di attivazione della categoria\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Categorie per l\'\'organizzazione dei menu e delle dashboard\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'Identificativo univoco della categoria\' FIRST',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'Nome della categoria\' AFTER `id`',
                'database' => '`database` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `name`',
                'description' => '`description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Descrizione breve della categoria\' AFTER `database`',
                'icon' => '`icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Icona associata al menu (es. lucide-icon o SVG)\' AFTER `description`',
                'order' => '`order` int NOT NULL DEFAULT \'0\' COMMENT \'Ordine di visualizzazione nel menu\' AFTER `icon`',
                'is_active' => '`is_active` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Stato di attivazione della categoria\' AFTER `order`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `is_active`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
            ],
            'foreign' => [
            ],
        ],
        'password_reset_tokens' => [
            'create' => 'CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'email' => '`email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'token' => '`token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `email`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `token`',
            ],
            'indexes' => [
            ],
            'foreign' => [
            ],
        ],
        'projects' => [
            'create' => 'CREATE TABLE `projects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'Studio in corso\',
  `database` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_filters` json NOT NULL COMMENT \'Filtri coorte: [{column, from, to}]\',
  `value_filters` json DEFAULT NULL COMMENT \'Filtri coorte per valore: [{column, values: []}] (flag / lookup, IN)\',
  `is_current` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Studio attualmente selezionato dall\'\'utente\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `projects_user_id_is_current_index` (`user_id`,`is_current`),
  KEY `projects_company_id_foreign` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Studio per utente: memorizza i filtri di coorte in corso\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'user_id' => '`user_id` bigint unsigned DEFAULT NULL AFTER `id`',
                'company_id' => '`company_id` bigint unsigned DEFAULT NULL AFTER `user_id`',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'Studio in corso\' AFTER `company_id`',
                'database' => '`database` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `name`',
                'date_filters' => '`date_filters` json NOT NULL COMMENT \'Filtri coorte: [{column, from, to}]\' AFTER `database`',
                'value_filters' => '`value_filters` json DEFAULT NULL COMMENT \'Filtri coorte per valore: [{column, values: []}] (flag / lookup, IN)\' AFTER `date_filters`',
                'is_current' => '`is_current` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Studio attualmente selezionato dall\'\'utente\' AFTER `value_filters`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `is_current`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'projects_user_id_is_current_index' => 'KEY `projects_user_id_is_current_index` (`user_id`,`is_current`)',
                'projects_company_id_foreign' => 'KEY `projects_company_id_foreign` (`company_id`)',
            ],
            'foreign' => [
                'projects_company_id_foreign' => 'CONSTRAINT `projects_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL',
                'projects_user_id_foreign' => 'CONSTRAINT `projects_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE',
            ],
        ],
        'schema_legend_columns' => [
            'create' => 'CREATE TABLE `schema_legend_columns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `schema_legend_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nullable` tinyint(1) NOT NULL DEFAULT \'1\',
  `position` int unsigned NOT NULL DEFAULT \'0\',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Commento del campo dal database\',
  `date_category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Categoria semantica di data (DateFieldSemanticsMap)\',
  `date_ranges` json DEFAULT NULL COMMENT \'Preset di intervallo data associati (ResearchDateRangeResolver)\',
  `foreign_key_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome del vincolo/indice secondario che collega alla lookup\',
  `lookup_table` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Tabella di lookup collegata\',
  `lookup_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Colonna chiave della lookup\',
  `lookup_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Colonna etichetta della lookup\',
  `lookup_values` json DEFAULT NULL COMMENT \'Valori della lookup: [{value, label}]\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `schema_legend_columns_schema_legend_id_name_unique` (`schema_legend_id`,`name`),
  KEY `schema_legend_columns_lookup_table_index` (`lookup_table`)
) ENGINE=InnoDB AUTO_INCREMENT=382 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Legenda: campi di una tabella con comment, range di date e valori di lookup\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'schema_legend_id' => '`schema_legend_id` bigint unsigned NOT NULL AFTER `id`',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `schema_legend_id`',
                'data_type' => '`data_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `name`',
                'nullable' => '`nullable` tinyint(1) NOT NULL DEFAULT \'1\' AFTER `data_type`',
                'position' => '`position` int unsigned NOT NULL DEFAULT \'0\' AFTER `nullable`',
                'comment' => '`comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Commento del campo dal database\' AFTER `position`',
                'date_category' => '`date_category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Categoria semantica di data (DateFieldSemanticsMap)\' AFTER `comment`',
                'date_ranges' => '`date_ranges` json DEFAULT NULL COMMENT \'Preset di intervallo data associati (ResearchDateRangeResolver)\' AFTER `date_category`',
                'foreign_key_name' => '`foreign_key_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome del vincolo/indice secondario che collega alla lookup\' AFTER `date_ranges`',
                'lookup_table' => '`lookup_table` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Tabella di lookup collegata\' AFTER `foreign_key_name`',
                'lookup_key' => '`lookup_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Colonna chiave della lookup\' AFTER `lookup_table`',
                'lookup_label' => '`lookup_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Colonna etichetta della lookup\' AFTER `lookup_key`',
                'lookup_values' => '`lookup_values` json DEFAULT NULL COMMENT \'Valori della lookup: [{value, label}]\' AFTER `lookup_label`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `lookup_values`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'schema_legend_columns_schema_legend_id_name_unique' => 'UNIQUE KEY `schema_legend_columns_schema_legend_id_name_unique` (`schema_legend_id`,`name`)',
                'schema_legend_columns_lookup_table_index' => 'KEY `schema_legend_columns_lookup_table_index` (`lookup_table`)',
            ],
            'foreign' => [
                'schema_legend_columns_schema_legend_id_foreign' => 'CONSTRAINT `schema_legend_columns_schema_legend_id_foreign` FOREIGN KEY (`schema_legend_id`) REFERENCES `schema_legends` (`id`) ON DELETE CASCADE',
            ],
        ],
        'schema_legends' => [
            'create' => 'CREATE TABLE `schema_legends` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'dbai\',
  `database` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `table_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome leggibile della tabella\',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Commento della tabella dal database\',
  `columns_count` int unsigned NOT NULL DEFAULT \'0\',
  `order` int unsigned NOT NULL DEFAULT \'0\',
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `schema_legends_connection_table_name_unique` (`connection`,`table_name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Legenda: descrizione delle tabelle del database (dbai)\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'connection' => '`connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'dbai\' AFTER `id`',
                'database' => '`database` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `connection`',
                'table_name' => '`table_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `database`',
                'label' => '`label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome leggibile della tabella\' AFTER `table_name`',
                'description' => '`description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT \'Commento della tabella dal database\' AFTER `label`',
                'columns_count' => '`columns_count` int unsigned NOT NULL DEFAULT \'0\' AFTER `description`',
                'order' => '`order` int unsigned NOT NULL DEFAULT \'0\' AFTER `columns_count`',
                'synced_at' => '`synced_at` timestamp NULL DEFAULT NULL AFTER `order`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `synced_at`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'schema_legends_connection_table_name_unique' => 'UNIQUE KEY `schema_legends_connection_table_name_unique` (`connection`,`table_name`)',
            ],
            'foreign' => [
            ],
        ],
        'sessions' => [
            'create' => 'CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'user_id' => '`user_id` bigint unsigned DEFAULT NULL AFTER `id`',
                'ip_address' => '`ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `user_id`',
                'user_agent' => '`user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `ip_address`',
                'payload' => '`payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `user_agent`',
                'last_activity' => '`last_activity` int NOT NULL AFTER `payload`',
            ],
            'indexes' => [
                'sessions_user_id_index' => 'KEY `sessions_user_id_index` (`user_id`)',
                'sessions_last_activity_index' => 'KEY `sessions_last_activity_index` (`last_activity`)',
            ],
            'foreign' => [
            ],
        ],
        'socialite_users' => [
            'create' => 'CREATE TABLE `socialite_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `provider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `socialite_users_provider_provider_id_unique` (`provider`,`provider_id`),
  KEY `socialite_users_user_id_foreign` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'user_id' => '`user_id` bigint unsigned NOT NULL AFTER `id`',
                'provider' => '`provider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `user_id`',
                'provider_id' => '`provider_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `provider`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `provider_id`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'socialite_users_provider_provider_id_unique' => 'UNIQUE KEY `socialite_users_provider_provider_id_unique` (`provider`,`provider_id`)',
                'socialite_users_user_id_foreign' => 'KEY `socialite_users_user_id_foreign` (`user_id`)',
            ],
            'foreign' => [
                'socialite_users_user_id_foreign' => 'CONSTRAINT `socialite_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
            ],
        ],
        'users' => [
            'create' => 'CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned DEFAULT NULL,
  `project_id` bigint unsigned DEFAULT NULL,
  `dashboard_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_admin` tinyint(1) DEFAULT \'1\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_company_id_foreign` (`company_id`),
  KEY `users_project_id_foreign` (`project_id`),
  KEY `users_dashboard_id_foreign` (`dashboard_id`)
) ENGINE=InnoDB AUTO_INCREMENT=129 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'company_id' => '`company_id` bigint unsigned DEFAULT NULL AFTER `id`',
                'project_id' => '`project_id` bigint unsigned DEFAULT NULL AFTER `company_id`',
                'dashboard_id' => '`dashboard_id` bigint unsigned DEFAULT NULL AFTER `project_id`',
                'name' => '`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `dashboard_id`',
                'email' => '`email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `name`',
                'email_verified_at' => '`email_verified_at` timestamp NULL DEFAULT NULL AFTER `email`',
                'password' => '`password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL AFTER `email_verified_at`',
                'remember_token' => '`remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `password`',
                'is_admin' => '`is_admin` tinyint(1) DEFAULT \'1\' AFTER `remember_token`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `is_admin`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'users_email_unique' => 'UNIQUE KEY `users_email_unique` (`email`)',
                'users_company_id_foreign' => 'KEY `users_company_id_foreign` (`company_id`)',
                'users_project_id_foreign' => 'KEY `users_project_id_foreign` (`project_id`)',
                'users_dashboard_id_foreign' => 'KEY `users_dashboard_id_foreign` (`dashboard_id`)',
            ],
            'foreign' => [
                'users_company_id_foreign' => 'CONSTRAINT `users_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL',
                'users_dashboard_id_foreign' => 'CONSTRAINT `users_dashboard_id_foreign` FOREIGN KEY (`dashboard_id`) REFERENCES `dashboards` (`id`) ON DELETE SET NULL',
                'users_project_id_foreign' => 'CONSTRAINT `users_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL',
            ],
        ],
        ];
    }
};
