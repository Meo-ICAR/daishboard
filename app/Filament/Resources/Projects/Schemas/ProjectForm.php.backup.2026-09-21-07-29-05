<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Support\CohortFilterCatalog;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Form dello studio: definisce i filtri di coorte applicati in modo trasversale
 * alle dashboard. Filtra le tabelle documentate (patients, patient_visits) per
 * campi data (intervallo o preset) e per valori di flag / tabelle di lookup con
 * selezione multipla (IN).
 */
class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Studio')
                    ->columnSpanFull()
                    ->description('Definisce i filtri applicati in modo trasversale alle dashboard ')
                    ->schema([
                        Select::make('user_id')
                            ->label('Utente')
                            ->relationship('user', 'name')
                            ->default(fn () => auth()->id())
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->default('Studio in corso'),
                        TextInput::make('database')
                            ->label('Database')
                            ->maxLength(255),
                        Toggle::make('is_current')
                            ->label('Restrizione in corso')
                            ->helperText('Solo la restrizione in corso viene applicata alle dashboard grafiche.')
                            ->default(true)
                            ->inline(false),
                    ]),

                Section::make('Filtri per data')
                    ->description('Restringono la coorte prima del raggruppamento. Scegli un preset oppure un intervallo personalizzato.')
                    ->schema([
                        Repeater::make('date_filters')
                            ->hiddenLabel()
                            ->addActionLabel('Aggiungi filtro data')
                            ->reorderable(false)
                            ->defaultItems(0)
                            ->columns(2)
                            ->itemLabel(fn (array $state): ?string => $state['column'] ?? null)
                            ->schema([
                                Select::make('column')
                                    ->label('Campo data')
                                    ->options(CohortFilterCatalog::dateColumnOptions())
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->columnSpanFull(),
                                Select::make('preset')
                                    ->label('Periodo')
                                    ->placeholder('Intervallo personalizzato')
                                    ->options(fn (Get $get): array => CohortFilterCatalog::datePresetOptions()[$get('column')] ?? [])
                                    ->disabled(fn (Get $get): bool => blank($get('column')))
                                    ->live()
                                    ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                        $range = CohortFilterCatalog::presetRange($get('column'), $state);
                                        $set('from', $range['from']);
                                        $set('to', $range['to']);
                                    })
                                    ->columnSpanFull(),
                                DatePicker::make('from')
                                    ->label('Da')
                                    ->native(false)
                                    ->disabled(fn (Get $get): bool => filled($get('preset')))
                                    ->dehydrated(),
                                DatePicker::make('to')
                                    ->label('A')
                                    ->native(false)
                                    ->disabled(fn (Get $get): bool => filled($get('preset')))
                                    ->dehydrated()
                                    ->afterOrEqual('from'),
                            ]),
                    ]),

                Section::make('Filtri per valore (flag / lookup)')
                    ->description('Seleziona uno o più valori ammessi: la coorte è limitata ai record che li soddisfano (IN). Filtri su campi diversi sono combinati in AND.')
                    ->schema([
                        Repeater::make('value_filters')
                            ->hiddenLabel()
                            ->addActionLabel('Aggiungi filtro valore')
                            ->reorderable(false)
                            ->defaultItems(0)
                            ->columns(2)
                            ->itemLabel(fn (array $state): ?string => $state['column'] ?? null)
                            ->schema([
                                Select::make('column')
                                    ->label('Campo')
                                    ->options(CohortFilterCatalog::valueColumnOptions())
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->columnSpanFull(),
                                Select::make('values')
                                    ->label('Valori ammessi')
                                    ->multiple()
                                    ->options(fn (Get $get): array => CohortFilterCatalog::valueOptions()[$get('column')] ?? [])
                                    ->disabled(fn (Get $get): bool => blank($get('column')))
                                    ->required()
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
