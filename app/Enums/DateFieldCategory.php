<?php

namespace App\Enums;

enum DateFieldCategory: string
{
    case BirthDate = 'birth_date';
    case EnrollmentDate = 'enrollment_date';
    case DiagnosisDate = 'diagnosis_date';
    case TherapyStartDate = 'therapy_start';
    case VisitDate = 'visit_date';
    case LabDate = 'lab_date';
    case EventDate = 'event_date';
    case SystemDate = 'system_date';
}
