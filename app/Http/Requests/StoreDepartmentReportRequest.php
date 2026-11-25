<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canCreateReports() ?? false;
    }

    public function rules(): array
    {
        return [
            'report_template_id' => ['required', 'exists:report_templates,id'],
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
