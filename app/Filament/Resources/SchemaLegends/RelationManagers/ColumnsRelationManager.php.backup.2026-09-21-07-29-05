<?php

namespace App\Filament\Resources\SchemaLegends\RelationManagers;

use App\Filament\Resources\LookupTables\LookupTableResource;
use App\Models\LookupTable;
use App\Models\SchemaLegendColumn;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ColumnsRelationManager extends RelationManager
{
    protected static string $relationship = 'columns';

    protected static ?string $title = 'Campi';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedTableCells;

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('position')
            ->paginated([25, 50, 100, 'all'])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('lookupTables:id,table_name,description'))
            ->columns([
                TextColumn::make('position')
                    ->label('#')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->label('Campo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('data_type')
                    ->label('Tipo')
                    ->badge()
                    ->color('gray'),
                IconColumn::make('nullable')
                    ->label('Null')
                    ->boolean(),
                TextColumn::make('comment')
                    ->label('Commento')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('date_category')
                    ->label('Categoria data')
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),
                TextColumn::make('summary')
                    ->label('Range date / Valori lookup')
                    ->state(fn (SchemaLegendColumn $record): ?string => $record->summary())
                    ->wrap()
                    ->limit(140)
                    ->tooltip(fn (SchemaLegendColumn $record): ?string => $record->summary())
                    ->placeholder('—'),
                TextColumn::make('lookup_table')
                    ->label('Lookup')
                    ->badge()
                    ->color('warning')
                    ->placeholder('—')
                    ->state(fn (SchemaLegendColumn $record): ?string => $record->lookup_table
                        ?? $record->lookupTables->pluck('table_name')->first())
                    ->icon(fn (SchemaLegendColumn $record): ?Heroicon => $this->resolveLookupTable($record) !== null
                        ? Heroicon::OutlinedArrowTopRightOnSquare
                        : null)
                    ->iconPosition(IconPosition::After)
                    ->url(fn (SchemaLegendColumn $record): ?string => ($lookup = $this->resolveLookupTable($record)) !== null
                        ? LookupTableResource::getUrl('view', ['record' => $lookup])
                        : null)
                    ->openUrlInNewTab()
                    ->tooltip(fn (SchemaLegendColumn $record): ?string => match (true) {
                        $record->lookupTables->count() > 1 => 'Collegata anche a: '.$record->lookupTables->pluck('table_name')->implode(', '),
                        $this->resolveLookupTable($record) !== null => 'Apri i valori assumibili',
                        default => null,
                    })
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('date_category')
                    ->label('Campi data')
                    ->placeholder('Tutti')
                    ->trueLabel('Solo campi data')
                    ->falseLabel('Escludi campi data')
                    ->queries(
                        true: fn ($query) => $query->dateFields(),
                        false: fn ($query) => $query->whereNull('date_category')->whereRaw(
                            'LOWER(data_type) NOT IN ('.implode(',', array_fill(0, count(SchemaLegendColumn::DATE_TYPES), '?')).')',
                            SchemaLegendColumn::DATE_TYPES,
                        ),
                        blank: fn ($query) => $query,
                    ),
                TernaryFilter::make('lookup_table')
                    ->label('Campi lookup')
                    ->placeholder('Tutti')
                    ->trueLabel('Solo campi lookup')
                    ->falseLabel('Escludi campi lookup')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('lookup_table'),
                        false: fn ($query) => $query->whereNull('lookup_table'),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->recordActions([
                Action::make('openLookup')
                    ->label('Valori lookup')
                    ->icon(Heroicon::OutlinedListBullet)
                    ->color('warning')
                    ->visible(fn (SchemaLegendColumn $record): bool => $this->resolveLookupTable($record) !== null)
                    ->url(fn (SchemaLegendColumn $record): ?string => ($lookup = $this->resolveLookupTable($record)) !== null
                        ? LookupTableResource::getUrl('view', ['record' => $lookup])
                        : null)
                    ->openUrlInNewTab(),
                Action::make('view')
                    ->label('Dettaglio')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading(fn (SchemaLegendColumn $record): string => "Campo · {$record->name}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Chiudi')
                    ->schema(fn (Schema $schema): Schema => self::detailSchema($schema)),
            ])
            ->toolbarActions([]);
    }

    /**
     * Tabella lookup collegata al campo: prima quella agganciata dal pivot,
     * altrimenti quella omonima all'hint `lookup_table`.
     */
    protected function resolveLookupTable(SchemaLegendColumn $column): ?LookupTable
    {
        $linked = $column->relationLoaded('lookupTables')
            ? $column->lookupTables->first()
            : $column->lookupTables()->first();

        if ($linked !== null) {
            return $linked;
        }

        if ($column->lookup_table === null) {
            return null;
        }

        return LookupTable::query()
            ->where('table_name', $column->lookup_table)
            ->first();
    }

    protected static function detailSchema(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextEntry::make('name')->label('Campo'),
                    TextEntry::make('data_type')->label('Tipo')->badge(),
                    TextEntry::make('nullable')->label('Nullable')->badge()
                        ->formatStateUsing(fn (bool $state): string => $state ? 'sì' : 'no'),
                    TextEntry::make('date_category')->label('Categoria data')->badge()->placeholder('—'),
                    TextEntry::make('comment')->label('Commento')->columnSpanFull()->placeholder('—'),
                ]),

            Section::make('Range di date associati')
                ->visible(fn (SchemaLegendColumn $record): bool => filled($record->date_ranges))
                ->schema([
                    RepeatableEntry::make('date_ranges')
                        ->hiddenLabel()
                        ->columns(3)
                        ->schema([
                            TextEntry::make('label')->label('Preset'),
                            TextEntry::make('from')->label('Da')->placeholder('—'),
                            TextEntry::make('to')->label('A')->placeholder('—'),
                        ]),
                ]),

            Section::make(fn (SchemaLegendColumn $record): string => 'Valori lookup · '.$record->lookup_table)
                ->visible(fn (SchemaLegendColumn $record): bool => filled($record->lookup_values))
                ->schema([
                    TextEntry::make('foreign_key_name')->label('Indice secondario / vincolo')->placeholder('euristica'),
                    RepeatableEntry::make('lookup_values')
                        ->hiddenLabel()
                        ->columns(2)
                        ->schema([
                            TextEntry::make('value')->label('Valore')->placeholder('∅'),
                            TextEntry::make('label')->label('Etichetta')->placeholder('∅'),
                        ]),
                ]),

            Section::make('Relazione')
                ->visible(fn (SchemaLegendColumn $record): bool => $record->lookup_table !== null && blank($record->lookup_values))
                ->schema([
                    TextEntry::make('lookup_table')
                        ->label('Collegata a')
                        ->badge()
                        ->url(fn (SchemaLegendColumn $record): ?string => ($lookup = LookupTable::query()
                            ->where('table_name', $record->lookup_table)->first()) !== null
                            ? LookupTableResource::getUrl('view', ['record' => $lookup])
                            : null)
                        ->openUrlInNewTab(),
                    TextEntry::make('lookup_key')->label('Chiave'),
                    TextEntry::make('foreign_key_name')->label('Vincolo')->placeholder('euristica'),
                ]),

            Section::make('Tabelle lookup collegate')
                ->description('Clicca una tabella per vedere i valori assumibili.')
                ->visible(fn (SchemaLegendColumn $record): bool => $record->lookupTables()->exists())
                ->schema([
                    RepeatableEntry::make('lookupTables')
                        ->hiddenLabel()
                        ->columns(2)
                        ->schema([
                            TextEntry::make('table_name')
                                ->label('Tabella')
                                ->badge()
                                ->color('warning')
                                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                                ->iconPosition(IconPosition::After)
                                ->url(fn (LookupTable $record): string => LookupTableResource::getUrl('view', ['record' => $record]))
                                ->openUrlInNewTab(),
                            TextEntry::make('description')->label('Descrizione')->placeholder('—'),
                        ]),
                ]),
        ]);
    }
}
