<?php

return [
    /*
     * Connessione da ispezionare per costruire la legenda.
     */
    'connection' => 'dbai',

    /*
     * Fallback delle tabelle da documentare quando il database collegato non ha
     * un profilo in config/data_navigator.php. Di norma le tabelle principali
     * sono definite per-database in quel file ed è `legend:sync` a leggerle.
     */
    'tables' => [
        'patients',
        'patient_visits',
    ],

    /*
     * Numero massimo di valori di lookup memorizzati per campo.
     */
    'lookup_value_limit' => 500,

    /*
     * Oltre questo numero di righe la tabella collegata è considerata
     * un'entità (relazione) e non un'enumerazione: si registra solo il
     * collegamento, senza elencare i valori.
     */
    'lookup_enum_max' => 150,

    /*
     * Tabelle escluse dal catalogo lookup (framework / infrastruttura).
     * Supporta i pattern di Str::is (*).
     */
    'lookup_exclude' => [
        'cache',
        'cache_locks',
        'cache_logs',
        'jobs',
        'job_batches',
        'failed_jobs',
        'migrations',
        'sessions',
        'password_reset_tokens',
        'personal_access_tokens',
        'social_accounts',
        '*_phinxlog',
    ],
];
