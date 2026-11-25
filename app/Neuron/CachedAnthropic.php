<?php

declare(strict_types=1);

namespace App\Neuron;

use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;

/**
 * Provider Anthropic con prompt caching: il system prompt (schema incluso) e
 * le definizioni degli strumenti vengono marcati come cacheable, così i giri
 * successivi dell'agente (chiamate agli strumenti) e le domande ravvicinate
 * pagano la parte ripetuta a una frazione del prezzo di input.
 *
 * La libreria reimposta il system prompt come semplice testo a ogni richiesta
 * (ChatNode), perciò la conversione in blocco con cache_control avviene qui.
 */
class CachedAnthropic extends Anthropic
{
    public function systemPrompt(?string $prompt): AIProviderInterface
    {
        if (blank($prompt)) {
            return parent::systemPrompt($prompt);
        }

        return $this->systemPromptBlocks([
            ['type' => 'text', 'text' => $prompt, 'cache_control' => ['type' => 'ephemeral']],
        ]);
    }
}
