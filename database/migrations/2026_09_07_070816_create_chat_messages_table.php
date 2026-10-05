<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store per-messaggio usato da NeuronAI EloquentChatHistory per l'assistente dati.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->comment('Messaggi delle conversazioni con l\'assistente AI (NeuronAI)');

            $table->id();
            $table->string('thread_id')->index();
            $table->string('role');
            $table->json('content')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['thread_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
