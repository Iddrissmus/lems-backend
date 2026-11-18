<?php

namespace App\Http\Requests\Audit;

use App\Domain\Audit\Actions\ListAuditLogs;
use Illuminate\Foundation\Http\FormRequest;

class ListAuditLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // checked by AuditLogPolicy in the controller
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // Exact event ("user.disabled") or a whole group ("user.*", "auth.*").
            'event' => ['nullable', 'string', 'max:64', 'regex:/^[a-z_]+(\.([a-z_]+|\*))?$/'],
            'actor' => ['nullable', 'string', 'size:26'],
            'subject_type' => ['nullable', 'string', 'max:32'],
            'subject_id' => ['nullable', 'string', 'size:26'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.ListAuditLogs::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
