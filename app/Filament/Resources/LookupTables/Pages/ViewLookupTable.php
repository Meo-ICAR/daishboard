<?php

namespace App\Filament\Resources\LookupTables\Pages;

use App\Filament\Resources\LookupTables\LookupTableResource;
use App\Models\LookupTable;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

class ViewLookupTable extends ViewRecord
{
    protected static string $resource = LookupTableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('liveValues')
                ->label('Mostra valori')
                ->icon(Heroicon::OutlinedListBullet)
                ->color('gray')
                ->modalHeading(fn (): string => 'Valori · '.$this->record->table_name)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Chiudi')
                ->modalContent(function (): View {
                    /** @var LookupTable $record */
                    $record = $this->record;

                    $values = $record->values ?: $record->liveValues();

                    return view('filament.resources.lookup-tables.partials.values', [
                        'values' => $values,
                        'keyColumn' => $record->key_column,
                        'labelColumn' => $record->label_column,
                    ]);
                }),

            EditAction::make()
                ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
        ];
    }
}
