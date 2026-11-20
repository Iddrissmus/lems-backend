<?php

namespace App\Http\Requests\Academics;

use App\Models\RegistrationWindow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRegistrationWindowsRequest extends FormRequest
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
            'semester' => ['nullable', 'string', 'size:26'],
            'status' => ['nullable', Rule::in([RegistrationWindow::NOT_YET_OPEN, RegistrationWindow::OPEN, RegistrationWindow::CLOSED])],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
