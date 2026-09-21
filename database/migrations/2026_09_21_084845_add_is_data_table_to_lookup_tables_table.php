<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lookup_tables', function (Blueprint $table) {
            $table->boolean('is_data_table')->default(false)->after('is_dictionary')
                ->comment('Classificazione manuale del superadmin: true = tabella dati/relazione, non una vera lookup. Non toccata da legend:sync.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lookup_tables', function (Blueprint $table) {
            $table->dropColumn('is_data_table');
        });
    }
};
