<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lookup_tables', function (Blueprint $table) {
            $table->comment('Tabelle di lookup/dizionario del database (dbai), collegabili ai campi della legenda');

            $table->id();
            $table->string('connection')->default('dbai');
            $table->string('table_name');
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->string('key_column')->default('id');
            $table->string('label_column')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->boolean('is_dictionary')->default(true)->comment('True se piccola enumerazione con valori elencati');
            $table->json('values')->nullable()->comment('Valori: [{value, label}]');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['connection', 'table_name']);
        });

        Schema::create('lookup_table_column', function (Blueprint $table) {
            $table->comment('Collegamento lookup <-> campo di patients/patient_visits');

            $table->id();
            $table->foreignId('lookup_table_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schema_legend_column_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['lookup_table_id', 'schema_legend_column_id'], 'lookup_column_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lookup_table_column');
        Schema::dropIfExists('lookup_tables');
    }
};
