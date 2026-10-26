<?php

namespace App\Support;

/**
 * Visualizzazione dei campi data nelle tabelle: solo giorno (gg/mm/aaaa), mai
 * ora o minuti.
 */
class DateDisplay
{
    private const PATTERN = '/^(\d{4})-(\d{2})-(\d{2})(?:[ T]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})?)?$/';

    /**
     * Converte date/datetime in formato SQL o ISO in `d/m/Y`; ogni altro valore
     * è restituito invariato.
     */
    public static function format(mixed $value): mixed
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value, $m) !== 1) {
            return $value;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "{$m[3]}/{$m[2]}/{$m[1]}" : $value;
    }
}
