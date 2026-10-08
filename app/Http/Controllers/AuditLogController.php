<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * 系統審計日誌列表 (僅管理員)
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (!$user->isAdmin()) {
            abort(403, '僅系統管理員具備檢視審計稽核日誌之權限。');
        }

        $query = AuditLog::with('user:id,name,email,role,job_title')
            ->latest('id');

        // 動作篩選
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        // 關鍵字搜尋 (描述、IP、變更對象)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $logs = $query->paginate(20)->withQueryString()->through(function ($log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                    'role' => $log->user->role,
                ] : null,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'details' => $log->details,
                'created_at' => $log->created_at->toDateTimeString(),
            ];
        });

        // 統計各類別動作總計
        $actionTypes = AuditLog::selectRaw('action, count(*) as count')
            ->groupBy('action')
            ->pluck('count', 'action');

        return Inertia::render('AuditLogs/Index', [
            'logs' => $logs,
            'filters' => $request->only(['action', 'search']),
            'actionTypes' => $actionTypes,
            'totalLogs' => AuditLog::count(),
        ]);
    }
}
