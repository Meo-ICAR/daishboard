<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboards', function (Blueprint $table) {
            $table->comment('Contenitore principale per le dashboard personalizzate');

            $table->id()->comment('ID univoco della dashboard');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('Utente creatore/proprietario');
            $table->foreignId('menu_category_id')->nullable()->constrained('menu_categories')->nullOnDelete()->comment('Categoria di menu di appartenenza');
            $table->string('title')->comment('Titolo della dashboard');
            $table->text('description')->nullable()->comment('Descrizione dettagliata dello scopo della dashboard');
            $table->string('icon')->nullable()->comment('Icona rappresentativa');
            $table->integer('order')->default(0)->comment('Ordinamento nel menu');
            $table->boolean('is_active')->default(true)->comment('Indica se la dashboard è visibile');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboards');
    }
};
