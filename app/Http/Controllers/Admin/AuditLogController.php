<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditLogFilterRequest;
use App\Models\SecurityAuditLog;
use App\Services\SecurityAuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(AuditLogFilterRequest $request, SecurityAuditService $auditService): View
    {
        $logs = SecurityAuditLog::query()
            ->with('user')
            ->when($request->validated('event'), fn ($query, string $event) => $query->where('event', $event))
            ->when($request->validated('severity'), fn ($query, string $severity) => $query->where('severity', $severity))
            ->when($request->validated('user_id'), fn ($query, string $userId) => $query->where('user_id', $userId))
            ->when($request->validated('ip'), fn ($query, string $ip) => $query->where('ip_address', $ip))
            ->when($request->validated('route'), fn ($query, string $route) => $query->where('route', 'like', '%'.$route.'%'))
            ->when($request->validated('method'), fn ($query, string $method) => $query->where('method', mb_strtoupper($method)))
            ->when($request->validated('status'), fn ($query, int $status) => $query->where('status', $status))
            ->when($request->validated('from'), fn ($query, string $from) => $query->where('created_at', '>=', $from))
            ->when($request->validated('to'), fn ($query, string $to) => $query->where('created_at', '<=', $to))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $auditService->log($request, 'audit.viewed', 'info', 200, $request->user()->id, ['scope' => 'index']);

        return view('admin.audit-logs.index', ['logs' => $logs]);
    }

    public function show(Request $request, SecurityAuditLog $auditLog, SecurityAuditService $auditService): View
    {
        $this->authorize('view', $auditLog);
        $auditService->log($request, 'audit.viewed', 'info', 200, $request->user()->id, ['audit_log_id' => $auditLog->id]);

        return view('admin.audit-logs.show', ['auditLog' => $auditLog->load('user')]);
    }
}
