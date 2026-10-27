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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
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
                Select::make('company_id')
                    ->label(__('filament/admin/dashboard_resource.company_id'))
                    ->relationship('company', 'name')
                    ->helperText(__('filament/admin/dashboard_resource.company_id_help'))
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
                TextInput::make('database')
                    ->label(__('filament/admin/dashboard_resource.database'))
                    ->helperText(__('filament/admin/dashboard_resource.database_help'))
                    ->maxLength(255),
                TextInput::make('name')
                    ->label(__('filament/admin/dashboard_resource.name'))
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label(__('filament/admin/dashboard_resource.description'))
                    ->columnSpanFull(),
                TextInput::make('icon')
                    ->label(__('filament/admin/dashboard_resource.icon'))
                    ->placeholder('heroicon-o-chart-bar')
                    ->helperText(__('filament/admin/dashboard_resource.icon_help')),
                TextInput::make('order')
                    ->label(__('filament/admin/dashboard_resource.order'))
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label(__('filament/admin/dashboard_resource.is_active'))
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('order')
            ->emptyStateHeading(__('filament/admin/dashboard_resource.empty_heading'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament/admin/dashboard_resource.name'))
                    ->weight('medium')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('menuCategory.name')
                    ->label(__('filament/admin/dashboard_resource.menu_category.name'))
                    ->badge()
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('company.name')
                    ->label(__('filament/admin/dashboard_resource.company.name'))
                    ->placeholder(__('filament/admin/dashboard_resource.global'))
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('database')
                    ->label(__('filament/admin/dashboard_resource.database'))
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('widgets_count')
                    ->label(__('filament/admin/dashboard_resource.widgets_count'))
                    ->counts('widgets')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('filament/admin/dashboard_resource.user.name'))
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('icon')
                    ->label(__('filament/admin/dashboard_resource.icon'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('order')
                    ->label(__('filament/admin/dashboard_resource.order'))
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('filament/admin/dashboard_resource.is_active'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('filament/admin/dashboard_resource.created_at'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('filament/admin/dashboard_resource.updated_at'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('filament/admin/dashboard_resource.is_active'))
                    ->placeholder(__('filament/admin/dashboard_resource.all'))
                    ->trueLabel(__('filament/admin/dashboard_resource.active_only'))
                    ->falseLabel(__('filament/admin/dashboard_resource.inactive_only')),
                SelectFilter::make('menu_category_id')
                    ->label(__('filament/admin/dashboard_resource.menu_category_id'))
                    ->relationship('menuCategory', 'name'),
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
