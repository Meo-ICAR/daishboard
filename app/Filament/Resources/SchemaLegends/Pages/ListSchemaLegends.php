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
                ->label(__('filament/admin/list_schema_legends.sync'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false)
                ->requiresConfirmation()
                ->modalDescription('Rilegge lo schema del database e aggiorna la legenda delle tabelle configurate.')
                ->action(function (): void {
                    Artisan::call('legend:sync');

                    Notification::make()
                        ->title(__('filament/admin/list_schema_legends.legenda_sincronizzata'))
                        ->body(trim(Artisan::output()))
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getTitle(): string
    {
        return __('filament/admin/list_schema_legends.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin/list_schema_legends.title');
    }
}
