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
        Schema::table('dashboard_widgets', function (Blueprint $table) {
            $table->foreignId('master_widget_id')
                ->nullable()
                ->after('chat_history_id')
                ->constrained('dashboard_widgets')
                ->nullOnDelete()
                ->comment('Widget master di cui questo widget è un dettaglio/drill-down');

            $table->string('master_filter_column')
                ->nullable()
                ->after('master_widget_id')
                ->comment('Colonna della query del widget master su cui filtrare (es. n_pazienti, totale_centri)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dashboard_widgets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('master_widget_id');
            $table->dropColumn('master_filter_column');
        });
    }
};
