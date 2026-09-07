<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->json('value_filters')
                ->nullable()
                ->after('date_filters')
                ->comment('Filtri coorte per valore: [{column, values: []}] (flag / lookup, IN)');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('value_filters');
        });
    }
};
