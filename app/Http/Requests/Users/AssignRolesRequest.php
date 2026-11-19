<?php

namespace App\Http\Requests\Users;

use App\Domain\Identity\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRolesRequest extends FormRequest
{
    /** Permission is checked in the controller once the roles are validated. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
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
