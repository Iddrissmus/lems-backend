<?php

namespace App\Http\Requests\Users;

use App\Domain\Identity\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InviteUserRequest extends FormRequest
{
    /** Permission is checked in the controller once the roles are validated. */
    public function authorize(): bool
    {
        return true;
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['distinct', Rule::enum(Role::class)],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function roles(): array
    {
        /** @var array<int, string> */
        return array_values($this->validated('roles'));
    }
}
