<?php

namespace App\Filament\Resources\DashboardWidgets;

use App\Filament\Resources\DashboardWidgets\Pages\CreateDashboardWidget;
use App\Filament\Resources\DashboardWidgets\Pages\EditDashboardWidget;
use App\Filament\Resources\DashboardWidgets\Pages\ListDashboardWidgets;
use App\Filament\Resources\DashboardWidgets\Schemas\DashboardWidgetForm;
use App\Filament\Resources\DashboardWidgets\Tables\DashboardWidgetsTable;
use App\Models\DashboardWidget;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DashboardWidgetResource extends Resource
{
    protected static ?string $model = DashboardWidget::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DashboardWidgetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DashboardWidgetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDashboardWidgets::route('/'),
            'create' => CreateDashboardWidget::route('/create'),
            'edit' => EditDashboardWidget::route('/{record}/edit'),
        ];
    }
}
