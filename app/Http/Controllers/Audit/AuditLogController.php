<?php

namespace App\Http\Controllers\Audit;

use App\Domain\Audit\Actions\ListAuditLogs;
use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\ListAuditLogsRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    /** Read-only on purpose: there is no endpoint to create, edit or delete entries. */
    public function index(ListAuditLogsRequest $request, ListAuditLogs $listAuditLogs): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AuditLog::class);

        return AuditLogResource::collection($listAuditLogs->execute($request->validated()));
    }
}
