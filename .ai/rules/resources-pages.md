---
paths:
  - 'app/Filament/Resources/**/Pages/View*.php'
---

# Resources Pages

## Modali "valori" e "link condivisi": schema Filament, non blade
I contenuti di modale un tempo resi con view() sono ora schema Filament 5 (niente più resources/views/filament/resources/.../partials). ViewLookupTable::liveValues usa ->schema() con RepeatableEntry->state($record->values ?: $record->liveValues()) + TextEntry (ramo a 1 o 2 colonne su label_column). ViewDashboardWidget::sharesList usa ->schema() con RepeatableEntry->state($this->shareLinks()) e TextEntry->copyable() per il link (niente più Alpine clipboard); shareLinks(): array è pubblico e testabile. Rimossi: partial shares-list.blade.php, metodo revokeShare() per-riga e prop lastShareUrl + banner nella blade (view-dashboard-widget.blade.php ora è solo {{ $this->table }}, minimo obbligatorio per una Page con tabella). La revoca è solo massiva: Action revokeShares (ora ->visible quando esistono share). L'azione share invia una Notification persistente con una Action 'Apri' (classe Filament\Actions\Action — in Filament 5 NON esiste Filament\Notifications\Actions\Action). Test: WidgetShareModalTest, LookupTableValuesModalTest (assertano mount+assertHasNoActionErrors, non l'HTML del modale che i test Livewire non catturano per le table action).
