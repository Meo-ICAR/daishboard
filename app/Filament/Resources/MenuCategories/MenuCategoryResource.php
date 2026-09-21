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

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected static string|UnitEnum|null $navigationGroup = 'Impostazioni';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('filament/admin/menu_category_resource.name'))
                    ->required(),
                TextInput::make('database')
                    ->label(__('filament/admin/menu_category_resource.database'))
                    ->maxLength(255),
                TextInput::make('description')
                    ->label(__('filament/admin/menu_category_resource.description')),
                TextInput::make('icon')
                    ->label(__('filament/admin/menu_category_resource.icon')),
                TextInput::make('order')
                    ->label(__('filament/admin/menu_category_resource.order'))
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label(__('filament/admin/menu_category_resource.is_active'))
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
                    ->label(__('filament/admin/menu_category_resource.name'))
                    ->searchable(),
                TextColumn::make('database')
                    ->label(__('filament/admin/menu_category_resource.database'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('description')
                    ->label(__('filament/admin/menu_category_resource.description'))
                    ->searchable(),
                TextColumn::make('icon')
                    ->label(__('filament/admin/menu_category_resource.icon'))
                    ->searchable(),
                TextColumn::make('order')
                    ->label(__('filament/admin/menu_category_resource.order'))
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('filament/admin/menu_category_resource.is_active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('filament/admin/menu_category_resource.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('filament/admin/menu_category_resource.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->label(__('filament/admin/menu_category_resource.edit')),
                DeleteAction::make()
                    ->label(__('filament/admin/menu_category_resource.delete')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('filament/admin/menu_category_resource.delete_bulk')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMenuCategories::route('/'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin/menu_category_resource.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filament/admin/menu_category_resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament/admin/menu_category_resource.plural_model_label');
    }
}
