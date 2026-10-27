<?php

// Traduzioni italiane mancanti nel pacchetto: integrate qui (override in lang/vendor).
return [
    "column_manager" => [
        "position" => 'Posizione :position di :count.',
        "position_in_group" => 'Posizione :position di :count in :group.',
        "reordered" => ':column spostata in posizione :position di :count.',
        "reordered_deferred" => ':column spostata in posizione :position di :count. Applica le colonne per salvare le modifiche.',
        "reordered_in_group" => ':column spostata in posizione :position di :count in :group.',
        "reordered_in_group_deferred" => ':column spostata in posizione :position di :count in :group. Applica le colonne per salvare le modifiche.',
        "actions" => [
            "move_up" => [
                "label" => 'Sposta :column su',
            ],
            "move_down" => [
                "label" => 'Sposta :column giù',
            ],
            "reorder" => [
                "label" => 'Riordina colonna',
            ],
        ],
    ],
    "columns" => [
        "editable" => [
            "saving" => 'Salvataggio in corso...',
            "saved" => 'Modifica salvata.',
            "failure" => 'Non è stato possibile confermare il salvataggio. Aggiorna la pagina prima di riprovare.',
            "unavailable" => 'Questo elemento non è più modificabile.',
        ],
        "icon" => [
            "boolean" => [
                "true" => 'Sì',
                "false" => 'No',
            ],
        ],
    ],
    "actions" => [
        "reorder_record" => [
            "label" => 'Riordina elemento :key',
        ],
        "move_record_up" => [
            "label" => 'Sposta :label su',
        ],
        "move_record_down" => [
            "label" => 'Sposta :label giù',
        ],
        "toggle_record_content" => [
            "label" => 'Espandi/comprimi elemento :key',
        ],
    ],
    "loading" => 'Caricamento...',
    "reordering" => [
        "position" => ':label, posizione :position di :count.',
        "moved" => ':label spostato in posizione :position di :count.',
    ],
    "result_count" => '{0} Nessun risultato|{1} :count risultato|[2,*] :count risultati',
];
