<x-filament-panels::page>

    @if ($lastShareUrl)
        <div
            x-data="{ url: @js($lastShareUrl), copied: false }"
            style="display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;margin-bottom:1rem;padding:.75rem 1rem;border:1px solid rgb(134 239 172);background:rgb(240 253 244);border-radius:.75rem;font-size:.875rem"
            class="dw-share-banner"
        >
            <span style="font-weight:600;color:rgb(22 101 52)">Link pubblico creato:</span>
            <input
                readonly
                :value="url"
                x-on:focus="$el.select()"
                style="flex:1 1 16rem;min-width:0;padding:.25rem .5rem;border:1px solid rgb(187 247 208);border-radius:.5rem;background:#fff;font-family:ui-monospace,monospace;font-size:.75rem;color:#374151"
            />
            <button
                type="button"
                x-on:click="navigator.clipboard.writeText(url); copied = true; setTimeout(() => copied = false, 2000)"
                style="padding:.25rem .75rem;border:0;border-radius:.5rem;background:rgb(22 163 74);color:#fff;font-size:.75rem;font-weight:600;cursor:pointer"
            >
                <span x-text="copied ? 'Copiato!' : 'Copia'"></span>
            </button>
            <a
                :href="url"
                target="_blank"
                rel="noopener"
                style="padding:.25rem .75rem;border:1px solid rgb(187 247 208);border-radius:.5rem;color:rgb(21 128 61);font-size:.75rem;font-weight:600;text-decoration:none"
            >Apri</a>
        </div>
    @endif

    {{-- Il titolo del widget è nell'header della pagina e nell'intestazione della tabella. --}}
    {{ $this->table }}

</x-filament-panels::page>
