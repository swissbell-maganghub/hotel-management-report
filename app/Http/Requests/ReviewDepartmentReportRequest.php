<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewDepartmentReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $report = $this->route('report');

        return $report && $this->user()?->can('review', $report);
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:in_review,reviewed,returned'],
            'review_note' => ['required_if:decision,returned', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'review_note.required_if' => session('locale', 'id') === 'en'
                ? 'Revision note is required when returning a report.'
                : 'Catatan revisi wajib diisi saat mengembalikan laporan.',
        ];
    }
}
