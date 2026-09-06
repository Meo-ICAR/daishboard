<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->comment('Studio per utente: memorizza i filtri di coorte in corso');

            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('Studio in corso');
            $table->json('date_filters')->comment('Filtri coorte: [{column, from, to}]');
            $table->boolean('is_current')->default(true)->comment('Studio attualmente selezionato dall\'utente');
            $table->timestamps();

            $table->index(['user_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
