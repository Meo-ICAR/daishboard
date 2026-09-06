<?php

namespace App\Filament\Resources\DashboardWidgets\Pages;

use App\Filament\Resources\DashboardWidgets\DashboardWidgetResource;
use App\Models\DashboardWidget;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Throwable;
use BackedEnum;

class ViewDashboardWidget extends Page
{
    protected static string $resource = DashboardWidgetResource::class;

    protected string $view = 'filament.resources.dashboard-widgets.pages.view-dashboard-widget';

    /** @var DashboardWidget */
    public DashboardWidget $record;

    /** Colonne estratte dinamicamente dalla query */
    public array $columns = [];

    /** Righe risultanti dalla query */
    public array $rows = [];

    /** Eventuale messaggio di errore */
    public ?string $errorMessage = null;

    /** Indica se la query è in esecuzione */
    public bool $loading = false;

    public function mount(int|string $record): void
    {
        $this->record = DashboardWidget::findOrFail($record);

        $this->authorize('view', $this->record);

        $this->runQuery();
    }

    /**
     * Esegue la query SQL sulla connessione DBAI.
     * Accetta solo statement SELECT per sicurezza.
     */
    public function runQuery(): void
    {
        $this->columns = [];
        $this->rows    = [];
        $this->errorMessage = null;

        $query = trim((string) $this->record->query);

        if (empty($query)) {
            $this->errorMessage = 'Nessuna query definita per questo widget.';
            return;
        }

        // Sicurezza: consente solo istruzioni SELECT
        if (! preg_match('/^\s*SELECT\b/i', $query)) {
            $this->errorMessage = 'Sono consentite solo istruzioni SELECT.';
            return;
        }

        try {
            $results = DB::connection('dbai')->select($query);

            if (empty($results)) {
                $this->errorMessage = 'La query non ha restituito risultati.';
                return;
            }

            // Estrae le colonne dal primo record
            $this->columns = array_keys((array) $results[0]);

            // Converte ogni riga in array associativo
            $this->rows = array_map(
                fn ($row) => (array) $row,
                $results
            );
        } catch (Throwable $e) {
            $this->errorMessage = 'Errore nell\'esecuzione della query: ' . $e->getMessage();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runQuery')
                ->label('Esegui di nuovo')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->runQuery()),

            EditAction::make()
                ->record($this->record)
                ->url(DashboardWidgetResource::getUrl('edit', ['record' => $this->record])),
        ];
    }

    public function getTitle(): string
    {
        return $this->record->title ?? 'Widget #' . $this->record->id;
    }

    public function getBreadcrumbs(): array
    {
        return [
            DashboardWidgetResource::getUrl() => 'Dashboard Widget',
            '#' => $this->getTitle(),
        ];
    }
}
