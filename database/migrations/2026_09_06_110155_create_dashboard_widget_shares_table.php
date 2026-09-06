<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_widget_shares', function (Blueprint $table) {
            $table->comment('Link pubblici per condividere una tabella widget con parametri memorizzati');

            $table->id();
            $table->string('token', 64)->unique()->comment('Slug pubblico non indovinabile del link');
            $table->foreignId('dashboard_widget_id')->constrained()->cascadeOnDelete()->comment('Widget condiviso');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('Utente che ha creato il link');
            $table->string('title')->nullable()->comment('Titolo mostrato nella pagina pubblica');
            $table->json('parameters')->comment('Parametri memorizzati: dateFilters, ecc.');
            $table->boolean('include_children')->default(true)->comment('Consente di aprire le tabelle figlio dal link');
            $table->timestamp('expires_at')->nullable()->comment('Scadenza del link, null = nessuna');
            $table->timestamp('last_viewed_at')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widget_shares');
    }
};
