<?php

namespace App\Services;

use App\Enums\DateFieldCategory;

class DateFieldSemanticsMap
{
    protected array $map = [
        'patients' => [
            'datanascita' => DateFieldCategory::BirthDate,
            'annonascita' => DateFieldCategory::BirthDate,
            'arruolato' => DateFieldCategory::EnrollmentDate,
            'datahiv' => DateFieldCategory::DiagnosisDate,
            'positivodal' => DateFieldCategory::DiagnosisDate,
            'trattamentodal' => DateFieldCategory::TherapyStartDate,
            'cd4data' => DateFieldCategory::LabDate,
            'created' => DateFieldCategory::SystemDate,
            'modified' => DateFieldCategory::SystemDate,
        ],
        'patient_visits' => [
            'visitadel' => DateFieldCategory::VisitDate,
            'Trattamentonuovodal' => DateFieldCategory::TherapyStartDate,
            'created' => DateFieldCategory::SystemDate,
            'modified' => DateFieldCategory::SystemDate,
        ],
    ];

    public function categoryFor(string $table, string $column): ?DateFieldCategory
    {
        return $this->map[$table][$column] ?? null;
    }
}
