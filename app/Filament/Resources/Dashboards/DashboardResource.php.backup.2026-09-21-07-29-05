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
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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

    protected static string|UnitEnum|null $navigationGroup = 'Impostazioni';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

   // protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $modelLabel = 'dashboard';

    protected static ?string $pluralModelLabel = 'dashboard';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Utente')
                    ->relationship('user', 'name'),
                Select::make('menu_category_id')
                    ->label('Categoria menu')
                    ->relationship('menuCategory', 'name'),
                TextInput::make('title')
                    ->label('Titolo')
                    ->required(),
                Textarea::make('description')
                    ->label('Descrizione')
                    ->columnSpanFull(),
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
            ->columns([
                TextColumn::make('user.name')
                    ->label('Utente')
                    ->searchable(),
                TextColumn::make('menuCategory.name')
                    ->label('Categoria menu')
                    ->searchable(),
                TextColumn::make('title')
                    ->label('Titolo')
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
            'index' => ManageDashboards::route('/'),
        ];
    }
}
