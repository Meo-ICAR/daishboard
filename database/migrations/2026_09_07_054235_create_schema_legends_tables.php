<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schema_legends', function (Blueprint $table) {
            $table->comment('Legenda: descrizione delle tabelle del database (dbai)');

            $table->id();
            $table->string('connection')->default('dbai');
            $table->string('table_name');
            $table->string('label')->nullable()->comment('Nome leggibile della tabella');
            $table->text('description')->nullable()->comment('Commento della tabella dal database');
            $table->unsignedInteger('columns_count')->default(0);
            $table->unsignedInteger('order')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['connection', 'table_name']);
        });

        Schema::create('schema_legend_columns', function (Blueprint $table) {
            $table->comment('Legenda: campi di una tabella con comment, range di date e valori di lookup');

            $table->id();
            $table->foreignId('schema_legend_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('data_type')->nullable();
            $table->boolean('nullable')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->text('comment')->nullable()->comment('Commento del campo dal database');

            $table->string('date_category')->nullable()->comment('Categoria semantica di data (DateFieldSemanticsMap)');
            $table->json('date_ranges')->nullable()->comment('Preset di intervallo data associati (ResearchDateRangeResolver)');

            $table->string('foreign_key_name')->nullable()->comment('Nome del vincolo/indice secondario che collega alla lookup');
            $table->string('lookup_table')->nullable()->comment('Tabella di lookup collegata');
            $table->string('lookup_key')->nullable()->comment('Colonna chiave della lookup');
            $table->string('lookup_label')->nullable()->comment('Colonna etichetta della lookup');
            $table->json('lookup_values')->nullable()->comment('Valori della lookup: [{value, label}]');

            $table->timestamps();

            // Indice secondario per la ricerca dei campi all'interno della tabella.
            $table->unique(['schema_legend_id', 'name']);
            $table->index(['lookup_table']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schema_legend_columns');
        Schema::dropIfExists('schema_legends');
    }
};
