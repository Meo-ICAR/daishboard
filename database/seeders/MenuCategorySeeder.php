<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuCategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('menu_categories')->upsert([
            [
                'id' => 1,
                'name' => 'Produzione',
                'description' => null,
                'icon' => null,
                'order' => 0,
                'is_active' => 1,
                'created_at' => null,
                'updated_at' => null,
            ],
            [
                'id' => 2,
                'name' => 'Contabilita',
                'description' => null,
                'icon' => null,
                'order' => 0,
                'is_active' => 1,
                'created_at' => null,
                'updated_at' => null,
            ],
            [
                'id' => 3,
                'name' => 'Call Center',
                'description' => null,
                'icon' => null,
                'order' => 0,
                'is_active' => 1,
                'created_at' => null,
                'updated_at' => null,
            ],
            [
                'id' => 4,
                'name' => 'Anomalie',
                'description' => null,
                'icon' => null,
                'order' => 0,
                'is_active' => 1,
                'created_at' => null,
                'updated_at' => null,
            ],
        ], ['id'], ['name', 'description', 'icon', 'order', 'is_active', 'updated_at']);
    }
}
