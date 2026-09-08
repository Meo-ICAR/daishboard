<?php

declare(strict_types=1);

namespace App\Support;

use Filament\Support\Icons\Heroicon;

/**
 * Metadati sui tipi di grafico supportati da DashboardWidgetChart.
 * Usato dal form (Select con icone) e dalla table (IconColumn).
 */
class ChartType
{
    /**
     * Mappa tipo → [label, icona Heroicon, descrizione].
     *
     * @return array<string, array{label: string, icon: Heroicon, description: string}>
     */
    public static function all(): array
    {
        return [
            'bar' => [
                'label' => 'Barre',
                'icon' => Heroicon::OutlinedChartBar,
                'description' => 'Confronta valori tra categorie con barre verticali.',
            ],
            'line' => [
                'label' => 'Linee',
                'icon' => Heroicon::OutlinedChartBarSquare,
                'description' => 'Mostra l\'andamento di una o più serie nel tempo.',
            ],
            'pie' => [
                'label' => 'Torta',
                'icon' => Heroicon::OutlinedChartPie,
                'description' => 'Mostra le proporzioni di un insieme come fette.',
            ],
            'doughnut' => [
                'label' => 'Ciambella',
                'icon' => Heroicon::OutlinedChartPie,
                'description' => 'Come la torta, ma con centro vuoto per evidenziare il totale.',
            ],
            'polarArea' => [
                'label' => 'Area polare',
                'icon' => Heroicon::OutlinedStar,
                'description' => 'Simile alla torta, ma i raggi variano con il valore.',
            ],
            'radar' => [
                'label' => 'Radar',
                'icon' => Heroicon::OutlinedSignalSlash,
                'description' => 'Confronta più variabili su assi radiali sovrapposti.',
            ],
            'table' => [
                'label' => 'Tabella',
                'icon' => Heroicon::OutlinedSignalSlash,
                'description' => 'Solo tabella dati',
            ],
        ];
    }

    /**
     * Opzioni per Filament Select: value => label.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_map(fn (array $meta): string => $meta['label'], self::all());
    }

    /**
     * Icona Heroicon per un tipo (fallback a OutlinedChartBar).
     */
    public static function icon(string $type): Heroicon
    {
        return self::all()[$type]['icon'] ?? Heroicon::OutlinedChartBar;
    }

    /**
     * Label italiana per un tipo (fallback al tipo grezzo).
     */
    public static function label(string $type): string
    {
        return self::all()[$type]['label'] ?? $type;
    }
}
