<?php

namespace App\Filament\Resources\SchemaLegends\Pages;

use App\Filament\Resources\SchemaLegends\SchemaLegendResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSchemaLegend extends EditRecord
{
    protected static string $resource = SchemaLegendResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return __('filament/admin/edit_schema_legend.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin/edit_schema_legend.title');
    }
}
