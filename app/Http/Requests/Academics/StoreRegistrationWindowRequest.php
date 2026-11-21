<?php

namespace App\Http\Requests\Academics;

use Illuminate\Foundation\Http\FormRequest;

class StoreRegistrationWindowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // checked by the policy in the controller
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'semester_id' => ['required', 'string', 'size:26'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'audience' => ['nullable', 'array', 'max:50'],
            'audience.*.programme_id' => ['required', 'string', 'size:26'],
            'audience.*.year_of_study' => ['required', 'integer', 'between:1,10'],
        ];
    }
}
