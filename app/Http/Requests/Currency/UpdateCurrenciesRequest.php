<?php

declare(strict_types=1);

namespace App\Http\Requests\Currency;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCurrenciesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency_ids'   => ['required', 'array', 'min:1', 'max:5'],
            'currency_ids.*' => ['integer', 'exists:currencies,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'currency_ids.required' => 'Please select at least one currency.',
            'currency_ids.max'      => 'You may select up to 5 currencies.',
            'currency_ids.*.exists' => 'One or more selected currencies are invalid.',
        ];
    }
}
