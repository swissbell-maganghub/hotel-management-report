<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $report = $this->route('report');

        return $report && $this->user()?->can('update', $report);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'period_type' => ['required', 'in:daily,weekly,monthly'],
            'period_date' => ['required', 'date'],
            'summary_notes' => ['nullable', 'string', 'max:2000'],
            'rows_data' => ['required', 'string'],
            'intent' => ['required', 'in:draft,submit'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,xlsx,xls,csv,png,jpg,jpeg', 'max:5120'],
        ];
    }
}
