<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_histories', function (Blueprint $table) {
            $table->comment('Storico delle conversazioni e query analizzate dall\'AI');

            $table->id()->comment('ID univoco del record di cronologia');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('Utente proprietario della sessione di chat');
            $table->string('thread_id')->unique()->comment('Identificativo univoco del thread/sessione');
            $table->json('messages')->nullable()->comment('Contenuto strutturato dei messaggi e tool call in formato JSON');
            $table->timestamps();

            $table->index('thread_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_histories');
    }
};
