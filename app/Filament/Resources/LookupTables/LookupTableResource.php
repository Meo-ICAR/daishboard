<?php

namespace App\Filament\Resources\LookupTables;

use App\Filament\Resources\LookupTables\Pages\EditLookupTable;
use App\Filament\Resources\LookupTables\Pages\ListLookupTables;
use App\Filament\Resources\LookupTables\Pages\ViewLookupTable;
use App\Filament\Resources\LookupTables\RelationManagers\ColumnsRelationManager;
use App\Filament\Resources\LookupTables\Schemas\LookupTableForm;
use App\Filament\Resources\LookupTables\Schemas\LookupTableInfolist;
use App\Filament\Resources\LookupTables\Tables\LookupTablesTable;
use App\Models\LookupTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LookupTableResource extends Resource
{
    protected static ?string $model = LookupTable::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

   protected static string|UnitEnum|null $navigationGroup = 'Legenda';

    protected static ?string $navigationLabel = 'Tabelle lookup';

    protected static ?string $modelLabel = 'tabella lookup';

    protected static ?string $pluralModelLabel = 'tabelle lookup';

    protected static ?string $recordTitleAttribute = 'table_name';

    public static function form(Schema $schema): Schema
    {
        return LookupTableForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LookupTableInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LookupTablesTable::configure($table);
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
            'index' => ListLookupTables::route('/'),
            'view' => ViewLookupTable::route('/{record}'),
            'edit' => EditLookupTable::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
