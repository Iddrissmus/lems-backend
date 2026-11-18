<?php

namespace App\Http\Controllers\Audit;

use App\Domain\Audit\Actions\ListAuditLogs;
use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\ListAuditLogsRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

#[Group('Audit log', weight: 30)]
class AuditLogController extends Controller
{
    /** Read-only on purpose: there is no endpoint to create, edit or delete entries. */
    #[Endpoint(title: 'List audit log entries', description: 'Read-only, newest first. Requires permission audit.view (super_admin only for now). Filter with event (exact like user.disabled, or a group like user.* or auth.*), actor (user id), subject_type, subject_id, from and to (date-times), and page with page and per_page (max 100).')]
    public function index(ListAuditLogsRequest $request, ListAuditLogs $listAuditLogs): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AuditLog::class);

        return AuditLogResource::collection($listAuditLogs->execute($request->validated()));
    }
}
