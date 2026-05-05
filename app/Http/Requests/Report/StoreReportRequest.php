<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use App\Enums\ReportRange;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'range'       => ['required', Rule::enum(ReportRange::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'currency_id.exists' => 'The selected currency is invalid.',
        ];
    }
}
