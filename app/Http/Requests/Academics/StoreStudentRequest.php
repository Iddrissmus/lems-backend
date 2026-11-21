<?php

namespace App\Http\Requests\Academics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // checked by the policy in the controller
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Email is unique across the whole system (ADR 0010), including removed accounts.
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'programme_id' => ['required', 'string', 'size:26'],
            'cohort_id' => ['nullable', 'string', 'size:26'],
            'year_of_study' => ['nullable', 'integer', 'between:1,10'],
            'moh_membership_id' => ['nullable', 'string', 'max:30'],
            'course_offering_ids' => ['nullable', 'array', 'max:20'],
            'course_offering_ids.*' => ['string', 'size:26', 'distinct'],
        ];
    }
}
