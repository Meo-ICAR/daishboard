<?php

namespace App\Filament\Resources\Dashboards;

use App\Filament\Resources\Dashboards\Pages\ManageDashboards;
use App\Models\Dashboard;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class DashboardResource extends Resource
{
    protected static ?string $model = Dashboard::class;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static string|UnitEnum|null $navigationGroup = 'Impostazioni';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    // protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label(__('filament/admin/dashboard_resource.user_id'))
                    ->relationship('user', 'name'),
                Select::make('menu_category_id')
                    ->label(__('filament/admin/dashboard_resource.menu_category_id'))
                    ->relationship('menuCategory', 'name'),
                TextInput::make('name')
                    ->label(__('filament/admin/dashboard_resource.name'))
                    ->required(),
                Textarea::make('description')
                    ->label(__('filament/admin/dashboard_resource.description'))
                    ->columnSpanFull(),
                TextInput::make('icon')
                    ->label(__('filament/admin/dashboard_resource.icon')),
                TextInput::make('order')
                    ->label(__('filament/admin/dashboard_resource.order'))
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label(__('filament/admin/dashboard_resource.is_active'))
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('filament/admin/dashboard_resource.user.name'))
                    ->searchable(),
                TextColumn::make('menuCategory.name')
                    ->label(__('filament/admin/dashboard_resource.menu_category.name'))
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('filament/admin/dashboard_resource.name'))
                    ->searchable(),
                TextColumn::make('icon')
                    ->label(__('filament/admin/dashboard_resource.icon'))
                    ->searchable(),
                TextColumn::make('order')
                    ->label(__('filament/admin/dashboard_resource.order'))
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('filament/admin/dashboard_resource.is_active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('filament/admin/dashboard_resource.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('filament/admin/dashboard_resource.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->label(__('filament/admin/dashboard_resource.edit')),
                DeleteAction::make()
                    ->label(__('filament/admin/dashboard_resource.delete')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('filament/admin/dashboard_resource.delete_bulk')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDashboards::route('/'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin/dashboard_resource.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filament/admin/dashboard_resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament/admin/dashboard_resource.plural_model_label');
    }
}
