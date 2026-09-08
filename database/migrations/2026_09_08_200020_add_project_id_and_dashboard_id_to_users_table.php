<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ultima selezione fatta dall'utente (studio e dashboard), riproposta al login.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('project_id')
                ->nullable()
                ->after('company_id')
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('dashboard_id')
                ->nullable()
                ->after('project_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('project_id');
            $table->dropConstrainedForeignId('dashboard_id');
        });
    }
};
