<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DashboardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Recupera facoltativamente un ID utente e una categoria menu di esempio, se esistenti
        $userId = DB::table('users')->value('id');
        $menuCategoryId = DB::table('menu_categories')->value('id');

        $dashboards = [
            [
                'user_id' => $userId,
                'menu_category_id' => $menuCategoryId,
                'title' => 'Dashboard Generale',
                'description' => 'Panoramica principale con KPI, metriche globali e indicatori aziendali.',
                'icon' => 'heroicon-o-home',
                'order' => 1,
                'is_active' => true,
            ],
            [
                'user_id' => $userId,
                'menu_category_id' => $menuCategoryId,
                'title' => 'Analisi Pazienti & Visite',
                'description' => 'Statistiche relative ai pazienti, fasce d\'età, trattamenti e centri medici.',
                'icon' => 'heroicon-o-user-group',
                'order' => 2,
                'is_active' => true,
            ],
            [
                'user_id' => $userId,
                'menu_category_id' => $menuCategoryId,
                'title' => 'Gestione Provvigioni & Fatturazione',
                'description' => 'Monitoraggio provvigioni attive/passive, incassi, pagamenti ed Enasarco.',
                'icon' => 'heroicon-o-banknotes',
                'order' => 3,
                'is_active' => true,
            ],
            [
                'user_id' => $userId,
                'menu_category_id' => $menuCategoryId,
                'title' => 'Report Chiamate & Lead',
                'description' => 'Riepilogo chiamate effettuate, esiti, orari di picco e gestione dei lead caldi.',
                'icon' => 'heroicon-o-phone',
                'order' => 4,
                'is_active' => true,
            ],
        ];

        $now = now();

        foreach ($dashboards as $dashboard) {
            DB::table('dashboards')->insert(array_merge($dashboard, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }
}
