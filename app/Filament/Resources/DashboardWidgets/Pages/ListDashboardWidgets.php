<?php

namespace App\Filament\Resources\DashboardWidgets\Pages;

use App\Filament\Resources\DashboardWidgets\DashboardWidgetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDashboardWidgets extends ListRecords
{
    protected static string $resource = DashboardWidgetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return __('filament/admin/list_dashboard_widgets.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin/list_dashboard_widgets.title');
    }
}
