<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NeuronAI\Laravel\Models\ChatMessage as BaseChatMessage;
use Illuminate\Database\Eloquent\Builder;

/**
 * Estende il modello NeuronAI aggiungendo l'appartenenza a utente e company:
 * i nuovi messaggi ereditano automaticamente l'utente autenticato e la sua
 * company, così i filtri di visibilità funzionano.
 */
class ChatMessage extends BaseChatMessage
{
    protected $table = 'chat_messages';

    protected $fillable = [
        'thread_id',
        'role',
        'content',
        'meta',
        'company_id',
        'user_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            if ($message->user_id === null && auth()->check()) {
                $message->user_id = auth()->id();
            }

            if ($message->company_id === null && auth()->check()) {
                $message->company_id = auth()->user()->company_id;
            }

            
        });

         static::addGlobalScope('owned', function (Builder $builder): void {
            $userId = auth()->id();

            $builder->where(function (Builder $query) use ($userId): void {
                $query->whereNull('user_id')
                      ->orWhere('user_id', $userId);
            });
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
