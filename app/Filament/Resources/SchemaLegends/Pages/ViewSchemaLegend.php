<?php

namespace App\Filament\Resources\SchemaLegends\Pages;

use App\Filament\Resources\SchemaLegends\SchemaLegendResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSchemaLegend extends ViewRecord
{
    protected static string $resource = SchemaLegendResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
        ];
    }
}
