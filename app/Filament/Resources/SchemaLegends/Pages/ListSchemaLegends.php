<?php

namespace App\Filament\Resources\SchemaLegends\Pages;

use App\Filament\Resources\SchemaLegends\SchemaLegendResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;

class ListSchemaLegends extends ListRecords
{
    protected static string $resource = SchemaLegendResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Sincronizza schema')
                ->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->modalDescription('Rilegge lo schema del database e aggiorna la legenda delle tabelle configurate.')
                ->action(function (): void {
                    Artisan::call('legend:sync');

                    Notification::make()
                        ->title('Legenda sincronizzata')
                        ->body(trim(Artisan::output()))
                        ->success()
                        ->send();
                }),
        ];
    }
}
