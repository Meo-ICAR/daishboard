<?php

namespace App\Filament\Resources\DashboardWidgets\Pages;

use App\Filament\Resources\DashboardWidgets\DashboardWidgetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDashboardWidget extends EditRecord
{
    protected static string $resource = DashboardWidgetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return __('filament/admin/edit_dashboard_widget.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin/edit_dashboard_widget.title');
    }
}
