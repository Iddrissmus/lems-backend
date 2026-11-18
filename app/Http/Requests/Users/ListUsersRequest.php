<?php

namespace App\Http\Requests\Users;

use App\Domain\Identity\Actions\ListUsers;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // checked by UserPolicy in the controller
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $sorts = array_merge(ListUsers::SORTS, array_map(fn (string $s) => '-'.$s, ListUsers::SORTS));

        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'role' => ['nullable', Rule::enum(Role::class)],
            'sort' => ['nullable', Rule::in($sorts)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.ListUsers::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
