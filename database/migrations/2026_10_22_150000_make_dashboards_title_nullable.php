<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `dashboards.title` è la colonna storica del nome (oggi `name`): nello schema condiviso è ancora NOT NULL e senza default,
 * quindi ogni inserimento che scrive solo `name` fallisce. Diventa facoltativa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dashboards') && Schema::hasColumn('dashboards', 'title')) {
            DB::statement("ALTER TABLE `dashboards` MODIFY `title` VARCHAR(255) NULL COMMENT 'Titolo della dashboard (colonna storica, oggi si usa name)'");
        }
    }

    public function down(): void
    {
        // Rendere di nuovo obbligatoria la colonna storica farebbe fallire gli inserimenti di oggi: non si ripristina.
    }
};
