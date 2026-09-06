<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_categories', function (Blueprint $table) {
            $table->comment('Categorie per l\'organizzazione dei menu e delle dashboard');

            $table->id()->comment('Identificativo univoco della categoria');
            $table->string('name')->comment('Nome della categoria');
            $table->string('description')->nullable()->comment('Descrizione breve della categoria');
            $table->string('icon')->nullable()->comment('Icona associata al menu (es. lucide-icon o SVG)');
            $table->integer('order')->default(0)->comment('Ordine di visualizzazione nel menu');
            $table->boolean('is_active')->default(true)->comment('Stato di attivazione della categoria');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_categories');
    }
};
