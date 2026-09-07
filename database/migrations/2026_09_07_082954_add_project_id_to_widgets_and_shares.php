<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboard_widgets', function (Blueprint $table): void {
            $table->foreignId('project_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete()
                ->comment('Studio i cui filtri di coorte vengono applicati alla query');
        });

        Schema::table('dashboard_widget_shares', function (Blueprint $table): void {
            $table->foreignId('project_id')
                ->nullable()
                ->after('dashboard_widget_id')
                ->constrained()
                ->nullOnDelete()
                ->comment('Studio i cui filtri di coorte vengono applicati alla tabella pubblica');
        });
    }

    public function down(): void
    {
        Schema::table('dashboard_widgets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('dashboard_widget_shares', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
