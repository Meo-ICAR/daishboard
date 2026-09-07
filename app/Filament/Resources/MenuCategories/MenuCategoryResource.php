<?php

namespace App\Filament\Resources\MenuCategories;

use App\Filament\Resources\MenuCategories\Pages\ManageMenuCategories;
use App\Models\MenuCategory;
use App\Support\CompanyScope;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class MenuCategoryResource extends Resource
{
    protected static ?string $model = MenuCategory::class;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected static string|UnitEnum|null $navigationGroup = 'Impostazioni';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static ?string $navigationLabel = 'Categorie menu';

    protected static ?string $modelLabel = 'categoria menu';

    protected static ?string $pluralModelLabel = 'categorie menu';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required(),
                TextInput::make('database')
                    ->label('Database')
                    ->maxLength(255),
                TextInput::make('description')
                    ->label('Descrizione'),
                TextInput::make('icon')
                    ->label('Icona'),
                TextInput::make('order')
                    ->label('Ordine')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Attiva')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn ($query) => CompanyScope::byDatabase($query))
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('database')
                    ->label('Database')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->searchable(),
                TextColumn::make('icon')
                    ->label('Icona')
                    ->searchable(),
                TextColumn::make('order')
                    ->label('Ordine')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Attiva')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Creata il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Modificata il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMenuCategories::route('/'),
        ];
    }
}
