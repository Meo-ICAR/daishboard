<?php

namespace App\Http\Requests;

use App\Enums\DateRangePreset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExecuteDashboardQueryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'filters' => 'array',
            'filters.*.column' => 'required|string',
            'filters.*.preset' => ['nullable', Rule::enum(DateRangePreset::class)],
            'filters.*.from' => 'nullable|date|required_if:filters.*.preset,custom',
            'filters.*.to' => 'nullable|date|required_if:filters.*.preset,custom|after_or_equal:filters.*.from',
        ];
    }
}
