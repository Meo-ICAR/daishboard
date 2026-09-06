<?php

namespace App\Services;

use App\Enums\DateFieldCategory;
use Carbon\Carbon;

class ResearchDateRangeResolver
{
    public function __construct(protected DateRangeResolver $genericResolver) {}

    public function presetsFor(DateFieldCategory $category): array
    {
        return match ($category) {
            DateFieldCategory::BirthDate => $this->birthDatePresets(),
            DateFieldCategory::EnrollmentDate => $this->enrollmentPresets(),
            DateFieldCategory::DiagnosisDate, DateFieldCategory::TherapyStartDate => $this->hivEraPresets(),
            DateFieldCategory::VisitDate, DateFieldCategory::LabDate => $this->followUpPresets(),
            DateFieldCategory::EventDate => $this->followUpPresets(includeNever: true),
            DateFieldCategory::SystemDate => $this->genericResolver->allPresetsWithRanges(),
        };
    }

    protected function birthDatePresets(): array
    {
        $now = Carbon::now();
        $brackets = [
            ['label' => '18-29 anni', 'min' => 18, 'max' => 29],
            ['label' => '30-39 anni', 'min' => 30, 'max' => 39],
            ['label' => '40-49 anni', 'min' => 40, 'max' => 49],
            ['label' => '50-59 anni', 'min' => 50, 'max' => 59],
            ['label' => '60-69 anni', 'min' => 60, 'max' => 69],
            ['label' => '70+ anni', 'min' => 70, 'max' => 120],
        ];

        return collect($brackets)->map(fn ($b) => [
            'value' => 'age_'.$b['min'].'_'.$b['max'],
            'label' => $b['label'],
            'from' => $now->copy()->subYears($b['max'] + 1)->addDay()->toDateString(),
            'to' => $now->copy()->subYears($b['min'])->toDateString(),
        ])->all();
    }

    protected function enrollmentPresets(): array
    {
        $now = Carbon::now();

        return [
            ['value' => 'last_year', 'label' => 'Arruolati ultimo anno', 'from' => $now->copy()->subYear()->toDateString(), 'to' => $now->toDateString()],
            ['value' => 'last_5_years', 'label' => 'Arruolati ultimi 5 anni', 'from' => $now->copy()->subYears(5)->toDateString(), 'to' => $now->toDateString()],
            ['value' => 'historical', 'label' => 'Coorte storica (oltre 10 anni fa)', 'from' => null, 'to' => $now->copy()->subYears(10)->toDateString()],
        ];
    }

    /**
     * ATTENZIONE: cutoff indicativi da letteratura generale sull'evoluzione
     * della terapia ARV. Da confermare/adattare al contesto specifico della
     * coorte prima di un uso per pubblicazione — vedi config/hiv_eras.php.
     */
    protected function hivEraPresets(): array
    {
        $eras = config('hiv_eras.presets');

        return collect($eras)->map(fn ($era) => [
            'value' => $era['value'],
            'label' => $era['label'],
            'from' => $era['from'],
            'to' => $era['to'],
        ])->all();
    }

    protected function followUpPresets(bool $includeNever = false): array
    {
        $now = Carbon::now();

        $presets = [
            ['value' => 'last_year', 'label' => 'Ultimo anno', 'from' => $now->copy()->subYear()->toDateString(), 'to' => $now->toDateString()],
            ['value' => 'last_5_years', 'label' => 'Ultimi 5 anni', 'from' => $now->copy()->subYears(5)->toDateString(), 'to' => $now->toDateString()],
            ['value' => 'this_calendar_year', 'label' => 'Anno solare corrente', 'from' => $now->copy()->startOfYear()->toDateString(), 'to' => $now->copy()->endOfYear()->toDateString()],
        ];

        if ($includeNever) {
            $presets[] = ['value' => 'never_occurred', 'label' => 'Evento mai avvenuto', 'special' => 'is_null'];
        }

        return $presets;
    }
}
