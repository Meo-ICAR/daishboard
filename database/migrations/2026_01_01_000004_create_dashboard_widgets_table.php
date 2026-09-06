<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->comment('Widget grafici e tabelle generati dalle righe dello storico chat');

            $table->id()->comment('ID univoco del widget');
            $table->foreignId('dashboard_id')->constrained()->cascadeOnDelete()->comment('Dashboard genitore')->nullable();
            $table->foreignId('chat_history_id')->nullable()->constrained('chat_histories')->nullOnDelete()->comment('Riferimento alla riga di cronologia sorgente');
            $table->string('title')->comment('Titolo del widget')->nullable();
            $table->string('type')->comment('Tipo di componente (es. table, bar_chart, line_chart, kpi_card)')->nullable();
            $table->text('query')->nullable()->comment('Query SQL o definizione della vista generata');
            $table->json('settings')->nullable()->comment('Mappatura campi, assi X/Y, colori e filtri del grafico');
            $table->json('grid_position')->nullable()->comment('Posizionamento e dimensioni della griglia (x, y, width, height)');
            $table->integer('order')->default(0)->comment('Ordine di resa all\'interno della dashboard');
            $table->boolean('is_active')->default(true)->comment('Stato di visibilità del widget');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
    }
};
