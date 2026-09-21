<?php

namespace App\Filament\Resources\LookupTables\RelationManagers;

use App\Models\SchemaLegendColumn;
use App\Support\DataNavigatorProfile;
use BackedEnum;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Campi di patients / patient_visits collegati a questa tabella di lookup.
 */
class ColumnsRelationManager extends RelationManager
{
    protected static string $relationship = 'columns';

    protected static ?string $title = 'Campi collegati';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedLink;

    /**
     * Consente collega/scollega anche dalla pagina di sola visualizzazione.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Etichetta qualificata (tabella.campo) usata anche nella select di collegamento.
     */
    public function getRecordTitle(?Model $record): ?string
    {
        if ($record === null) {
            return null;
        }

        /** @var SchemaLegendColumn $record */
        return ($record->legend?->table_name ? "{$record->legend->table_name}." : '').$record->name;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('legend.table_name')
                    ->label('Tabella')
                    ->badge()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Campo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('data_type')
                    ->label('Tipo')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('comment')
                    ->label('Commento')
                    ->wrap()
                    ->limit(140),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Collega campi')
                    ->icon(Heroicon::OutlinedPlus)
                    ->multiple()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name'])
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query
                        ->with('legend:id,table_name')
                        ->whereHas('legend', fn (Builder $legend) => $legend->whereIn('table_name', DataNavigatorProfile::cohortTables()))),
            ])
            ->recordActions([
                DetachAction::make()->label('Scollega'),
            ])
            ->toolbarActions([
                DetachBulkAction::make(),
            ]);
    }
}
