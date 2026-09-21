<?php

namespace App\Filament\Resources\LookupTables\Pages;

use App\Filament\Resources\LookupTables\LookupTableResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;

class ListLookupTables extends ListRecords
{
    protected static string $resource = LookupTableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label(__('filament/admin/list_lookup_tables.sync'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false)
                ->requiresConfirmation()
                ->modalDescription('Rilegge tutte le tabelle del database e aggiorna il catalogo lookup e i collegamenti automatici.')
                ->action(function (): void {
                    Artisan::call('legend:sync');

                    Notification::make()
                        ->title(__('filament/admin/list_lookup_tables.catalogo_lookup_sincronizzato'))
                        ->body(trim(Artisan::output()))
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getTitle(): string
    {
        return __('filament/admin/list_lookup_tables.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin/list_lookup_tables.title');
    }
}
