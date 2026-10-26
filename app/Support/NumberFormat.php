<?php

namespace App\Support;

/**
 * Formattazione dei valori numerici delle tabelle: separatore delle migliaia
 * (punto) e decimali con la virgola, come da locale italiano.
 */
class NumberFormat
{
    /**
     * Colonne che sono identificatori o periodi (id, codici, anni, trimestri):
     * il separatore delle migliaia li renderebbe illeggibili ("2.026").
     */
    private const IDENTIFIER_PATTERN = '/(^|_)(id|codice|cod|anno|competenza|trimestre|piva|cap)(_|$)/i';

    public static function isIdentifierColumn(string $column): bool
    {
        return preg_match(self::IDENTIFIER_PATTERN, $column) === 1;
    }

    /**
     * Interi senza decimali, valori con parte frazionaria a due decimali.
     * I valori non numerici sono restituiti invariati.
     */
    public static function format(mixed $value, ?int $decimals = null): mixed
    {
        if (! is_numeric($value) || is_string($value) && preg_match('/^0\d/', $value) === 1) {
            return $value;
        }

        $number = (float) $value;

        return number_format($number, $decimals ?? ($number === floor($number) ? 0 : 2), ',', '.');
    }

    /**
     * Come format(), ma lascia intatte le colonne identificative.
     */
    public static function formatColumn(string $column, mixed $value, ?int $decimals = null): mixed
    {
        return self::isIdentifierColumn($column) ? $value : self::format($value, $decimals);
    }

    /**
     * Decimali comuni a tutta la colonna: 2 se almeno una riga ha una parte
     * frazionaria, altrimenti 0, così gli importi restano incolonnati.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function columnDecimals(array $rows, string $column): int
    {
        foreach ($rows as $row) {
            $value = $row[$column] ?? null;

            if (is_numeric($value) && (float) $value !== floor((float) $value)) {
                return 2;
            }
        }

        return 0;
    }
}
