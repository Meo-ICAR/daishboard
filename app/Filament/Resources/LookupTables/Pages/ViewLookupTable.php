<?php

namespace App\Filament\Resources\LookupTables\Pages;

use App\Exports\WidgetDatasetExport;
use App\Filament\Resources\LookupTables\LookupTableResource;
use App\Models\LookupTable;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ViewLookupTable extends ViewRecord
{
    protected static string $resource = LookupTableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('liveValues')
                ->label(__('filament/admin/view_lookup_table.live_values'))
                ->icon(Heroicon::OutlinedListBullet)
                ->color('gray')
                ->modalHeading(fn (): string => 'Valori · '.$this->record->table_name)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Chiudi')
                ->schema(function (): array {
                    /** @var LookupTable $record */
                    $record = $this->record;

                    $values = $record->values ?: $record->liveValues();

                    if ($values === []) {
                        return [
                            TextEntry::make('empty')
                                ->label(__('filament/admin/view_lookup_table.empty'))->hiddenLabel()->state(__('filament/admin/view_lookup_table.nessun_valore.')),
                        ];
                    }

                    $hasLabel = filled($record->label_column);

                    return [
                        TextEntry::make('count')
                            ->label(__('filament/admin/view_lookup_table.count'))
                            ->hiddenLabel()
                            ->color('gray')
                            ->state(count($values).' valori'),
                        RepeatableEntry::make('values')
                            ->label(__('filament/admin/view_lookup_table.values'))
                            ->hiddenLabel()
                            ->state($values)
                            ->columns($hasLabel ? 2 : 1)
                            ->schema(array_values(array_filter([
                                TextEntry::make('value')
                                    ->label(__('filament/admin/view_lookup_table.value'))
                                    ->weight('semibold')
                                    ->placeholder('∅'),
                                $hasLabel
                                    ? TextEntry::make('label')
                                        ->label(__('filament/admin/view_lookup_table.label'))
                                        ->color('gray')
                                        ->placeholder('∅')
                                    : null,
                            ]))),
                    ];
                }),

            Action::make('exportExcel')
                ->label(__('filament/admin/view_lookup_table.export_excel'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => filled($this->record->values) || filled($this->record->key_column))
                ->action(function () {
                    /** @var LookupTable $record */
                    $record = $this->record;

                    $keyHeading = $record->key_column ?: 'Valore';
                    $labelHeading = $record->label_column ?: 'Etichetta';

                    if ($labelHeading === $keyHeading) {
                        $labelHeading .= ' (etichetta)';
                    }

                    $values = $record->values ?: $record->liveValues();

                    $rows = array_map(static fn (array $value): array => [
                        $keyHeading => $value['value'] ?? null,
                        $labelHeading => $value['label'] ?? null,
                    ], $values);

                    $name = Str::slug($record->table_name.'-valori') ?: 'codifica';

                    return Excel::download(
                        new WidgetDatasetExport([$keyHeading, $labelHeading], $rows, $record->table_name),
                        $name.'-'.now()->format('Ymd-His').'.xlsx',
                    );
                }),

            EditAction::make()
                ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
        ];
    }

    public function getTitle(): string
    {
        return __('filament/admin/view_lookup_table.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/admin/view_lookup_table.title');
    }
}
