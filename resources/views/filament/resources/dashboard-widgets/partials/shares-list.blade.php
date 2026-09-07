<div style="display:flex;flex-direction:column;gap:.75rem">
    @forelse ($shares as $share)
        @php($url = $share->publicUrl())
        <div
            x-data="{ url: @js($url), copied: false }"
            style="display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;padding:.75rem;border:1px solid rgb(229 231 235);border-radius:.75rem"
        >
            <div style="flex:1 1 20rem;min-width:0">
                <div style="font-weight:600;font-size:.875rem;color:#111827">
                    {{ $share->title ?: ('Widget #'.$share->dashboard_widget_id) }}
                </div>
                <input
                    readonly
                    :value="url"
                    x-on:focus="$el.select()"
                    style="width:100%;margin-top:.25rem;padding:.2rem .4rem;border:1px solid rgb(229 231 235);border-radius:.375rem;background:#fff;font-family:ui-monospace,monospace;font-size:.7rem;color:#374151"
                />
                <div style="margin-top:.35rem;font-size:.7rem;color:#6b7280">
                    Creato {{ $share->created_at?->format('d/m/Y H:i') }}
                    &middot; {{ $share->views }} visite
                    &middot;
                    @if ($share->expires_at)
                        scade {{ $share->expires_at->format('d/m/Y') }}{{ $share->isExpired() ? ' (scaduto)' : '' }}
                    @else
                        nessuna scadenza
                    @endif
                    @if ($share->include_children)
                        &middot; figli inclusi
                    @endif
                </div>
            </div>
            <div style="display:flex;gap:.5rem;flex-shrink:0">
                <button
                    type="button"
                    x-on:click="navigator.clipboard.writeText(url); copied = true; setTimeout(() => copied = false, 2000)"
                    style="padding:.25rem .75rem;border:0;border-radius:.5rem;background:rgb(37 99 235);color:#fff;font-size:.75rem;font-weight:600;cursor:pointer"
                >
                    <span x-text="copied ? 'Copiato!' : 'Copia'"></span>
                </button>
                <a
                    :href="url"
                    target="_blank"
                    rel="noopener"
                    style="padding:.25rem .75rem;border:1px solid rgb(229 231 235);border-radius:.5rem;color:#374151;font-size:.75rem;font-weight:600;text-decoration:none"
                >Apri</a>
                <button
                    type="button"
                    wire:click="revokeShare({{ $share->id }})"
                    wire:confirm="Vuoi revocare questo link? Non sarà più accessibile."
                    style="padding:.25rem .75rem;border:0;border-radius:.5rem;background:rgb(220 38 38);color:#fff;font-size:.75rem;font-weight:600;cursor:pointer"
                >Revoca</button>
            </div>
        </div>
    @empty
        <p style="font-size:.875rem;color:#6b7280;font-style:italic">Nessun link creato.</p>
    @endforelse
</div>
