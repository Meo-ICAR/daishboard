<x-filament-panels::page>

    <style>
        .ai-wrap { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 1024px) { .ai-wrap { grid-template-columns: 15rem 1fr; } }
        .ai-threads { display: flex; flex-direction: column; gap: .25rem; }
        .ai-thread { text-align: left; padding: .45rem .6rem; border-radius: .5rem; font-size: .8rem; color: #374151; background: transparent; border: 0; cursor: pointer; display: block; width: 100%; }
        .ai-thread:hover { background: rgba(0,0,0,.05); }
        .ai-thread.active { background: rgba(59,130,246,.12); color: #1d4ed8; }
        .ai-thread .t-label { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ai-thread.active .t-label { font-weight: 600; }
        .ai-thread .t-meta { display: block; margin-top: .1rem; font-size: .68rem; color: #9ca3af; }
        .ai-chat { display: flex; flex-direction: column; min-height: 60vh; max-height: 74vh; border: 1px solid #e5e7eb; border-radius: .75rem; background: #fff; overflow: hidden; }
        .ai-log { flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: .75rem; }
        .ai-msg { max-width: 90%; padding: .6rem .85rem; border-radius: .75rem; font-size: .9rem; line-height: 1.5; white-space: normal; }
        .ai-msg.user { align-self: flex-end; background: #2563eb; color: #fff; }
        .ai-msg.assistant { align-self: flex-start; background: #f3f4f6; color: #111827; }
        .ai-msg :is(pre, code) { font-family: ui-monospace, monospace; font-size: .8rem; }
        .ai-msg pre { background: rgba(0,0,0,.06); padding: .6rem; border-radius: .5rem; overflow-x: auto; }
        .ai-msg.assistant table { border-collapse: collapse; margin: .4rem 0; font-size: .8rem; }
        .ai-msg.assistant th, .ai-msg.assistant td { border: 1px solid #d1d5db; padding: .2rem .45rem; }
        .ai-empty { margin: auto; color: #9ca3af; font-size: .85rem; text-align: center; }
        .ai-actions { align-self: flex-start; display: flex; flex-wrap: wrap; gap: .4rem; margin-top: -.35rem; }
        .ai-mk-widget { display: inline-flex; align-items: center; gap: .3rem; padding: .25rem .6rem; border: 1px solid #bfdbfe; border-radius: .5rem; background: #eff6ff; color: #1d4ed8; font-size: .75rem; font-weight: 600; cursor: pointer; }
        .ai-mk-widget:hover { background: #dbeafe; }
        .ai-form { border-top: 1px solid #e5e7eb; padding: .75rem; display: flex; gap: .5rem; align-items: flex-end; }
        .ai-input { flex: 1; resize: none; border: 1px solid #d1d5db; border-radius: .5rem; padding: .5rem .7rem; font: inherit; font-size: .9rem; min-height: 2.5rem; max-height: 9rem; }
        .ai-error { margin: .5rem 1rem 0; padding: .5rem .75rem; border-radius: .5rem; background: #fef2f2; color: #b91c1c; font-size: .8rem; }
        @media (prefers-color-scheme: dark) {
            .ai-chat { background: #111827; border-color: #1f2937; }
            .ai-msg.assistant { background: #1f2937; color: #e5e7eb; }
            .ai-thread { color: #d1d5db; }
            .ai-thread:hover { background: rgba(255,255,255,.06); }
            .ai-form, .ai-log { border-color: #1f2937; }
            .ai-input { background: #0b1120; color: #e5e7eb; border-color: #374151; }
            .ai-error { background: #200a0a; color: #fca5a5; }
        }
    </style>

    <div class="ai-wrap">
        <aside class="ai-threads">
            <span style="font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;color:#9ca3af;padding:0 .6rem">Conversazioni</span>
            @forelse ($this->recentThreads() as $t)
                <button type="button"
                        wire:key="thr-{{ $t['thread_id'] }}"
                        wire:click="openThread('{{ $t['thread_id'] }}')"
                        @class(['ai-thread', 'active' => $t['active']])
                        title="{{ $t['label'] }}">
                    <span class="t-label">{{ $t['label'] }}</span>
                    <span class="t-meta">
                        {{ $t['last_at']?->format('d/m/Y H:i') ?? '—' }} &middot; {{ $t['messages'] }} msg
                    </span>
                </button>
            @empty
                <span style="font-size:.75rem;color:#9ca3af;padding:0 .6rem">Nessuna conversazione.</span>
            @endforelse
        </aside>

        <div class="ai-chat">
            <div class="ai-log" x-data x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
                 x-on:ai-scroll.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
                @forelse ($messages as $i => $message)
                    <div class="ai-msg {{ $message['role'] === 'user' ? 'user' : 'assistant' }}">
                        {!! $message['html'] !!}
                    </div>

                    @if (! empty($message['sql']))
                        <div class="ai-actions">
                            @foreach ($message['sql'] as $j => $query)
                                <button type="button" class="ai-mk-widget"
                                        wire:click="mountAction('createWidget', { messageIndex: {{ $i }}, sqlIndex: {{ $j }} })">
                                    &plus; Crea widget @if (count($message['sql']) > 1) #{{ $j + 1 }} @endif
                                </button>
                            @endforeach
                        </div>
                    @endif
                @empty
                    <p class="ai-empty">
                        Chiedi in linguaggio naturale: l'assistente genera ed esegue query SQL di sola lettura
                        sul database di coorte e ti mostra i risultati.
                    </p>
                @endforelse

                <div wire:loading wire:target="send" class="ai-msg assistant">
                    <em>Sto elaborando…</em>
                </div>
            </div>

            @if ($error)
                <div class="ai-error">{{ $error }}</div>
            @endif

            <form wire:submit="send" class="ai-form"
                  x-on:submit="$dispatch('ai-scroll')"
                  x-data
                  x-on:keydown.enter.prevent="if (!$event.shiftKey) $root.requestSubmit()">
                <textarea wire:model="prompt" class="ai-input" rows="1"
                          placeholder="Es. quanti pazienti per centro arruolati dopo il 2015?"
                          wire:loading.attr="disabled" wire:target="send"></textarea>
                <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="send">
                    Invia
                </x-filament::button>
            </form>
        </div>
    </div>

    <div wire:key="scroll-{{ count($messages) }}" x-data x-init="$dispatch('ai-scroll')"></div>

    <x-filament-actions::modals />

</x-filament-panels::page>
