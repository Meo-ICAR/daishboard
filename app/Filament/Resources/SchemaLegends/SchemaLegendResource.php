<?php

namespace App\Filament\Resources\SchemaLegends;

use App\Filament\Resources\SchemaLegends\Pages\EditSchemaLegend;
use App\Filament\Resources\SchemaLegends\Pages\ListSchemaLegends;
use App\Filament\Resources\SchemaLegends\Pages\ViewSchemaLegend;
use App\Filament\Resources\SchemaLegends\RelationManagers\ColumnsRelationManager;
use App\Filament\Resources\SchemaLegends\Schemas\SchemaLegendForm;
use App\Filament\Resources\SchemaLegends\Schemas\SchemaLegendInfolist;
use App\Filament\Resources\SchemaLegends\Tables\SchemaLegendsTable;
use App\Models\SchemaLegend;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SchemaLegendResource extends Resource
{
    protected static ?string $model = SchemaLegend::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Legenda';

    protected static ?string $navigationLabel = 'Dati';

    protected static ?string $modelLabel = 'tabella';

    protected static ?string $pluralModelLabel = 'legenda';

    protected static ?string $recordTitleAttribute = 'table_name';

    protected static ?int $navigationSort = 90;

    public static function form(Schema $schema): Schema
    {
        return SchemaLegendForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SchemaLegendInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SchemaLegendsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ColumnsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSchemaLegends::route('/'),
            'view' => ViewSchemaLegend::route('/{record}'),
            'edit' => EditSchemaLegend::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }
}
