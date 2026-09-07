<?php

namespace App\Filament\Resources\DashboardWidgets;

use App\Filament\Resources\DashboardWidgets\Pages\ChartDashboardWidget;
use App\Filament\Resources\DashboardWidgets\Pages\CreateDashboardWidget;
use App\Filament\Resources\DashboardWidgets\Pages\EditDashboardWidget;
use App\Filament\Resources\DashboardWidgets\Pages\ListDashboardWidgets;
use App\Filament\Resources\DashboardWidgets\Pages\ViewDashboardWidget;
use App\Filament\Resources\DashboardWidgets\Schemas\DashboardWidgetForm;
use App\Filament\Resources\DashboardWidgets\Tables\DashboardWidgetsTable;
use App\Models\DashboardWidget;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DashboardWidgetResource extends Resource
{
    protected static ?string $model = DashboardWidget::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    
    protected static string|UnitEnum|null $navigationGroup =  null;

    protected static ?string $navigationLabel = 'Grafici';

    protected static ?string $modelLabel = 'widget';

    protected static ?string $pluralModelLabel = 'widget grafici';

    protected static ?string $recordTitleAttribute = 'title';

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
            'view' => ViewDashboardWidget::route('/{record}'),
            'chart' => ChartDashboardWidget::route('/{record}/chart'),
            'edit' => EditDashboardWidget::route('/{record}/edit'),
        ];
    }
}
