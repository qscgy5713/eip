<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Webhook;
use App\Services\WebhookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebhookController extends Controller
{
    /**
     * 檢視 Webhook 清單 (管理員專屬)
     */
    public function index(Request $request): Response
    {
        if (!$request->user()->isAdmin()) {
            abort(403, '僅系統管理員具備管理外部整合 Webhook 之權限。');
        }

        $webhooks = Webhook::latest()->get();

        $supportedEvents = [
            'announcement.published' => '重要公告發布',
            'form.submitted' => '同仁提交新簽核申請單',
            'form.approved' => '主管核准通過簽核單據',
            'form.rejected' => '主管駁回退件簽核單據',
            'room.booked' => '會議室預約借用成立',
        ];

        return Inertia::render('Webhooks/Index', [
            'webhooks' => $webhooks,
            'supportedEvents' => $supportedEvents,
        ]);
    }

    /**
     * 新增 Webhook
     */
    public function store(Request $request): RedirectResponse
    {
        if (!$request->user()->isAdmin()) {
            abort(403, '僅系統管理員具備操作權限。');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string'],
            'secret' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        $webhook = Webhook::create([
            'name' => $validated['name'],
            'url' => $validated['url'],
            'events' => $validated['events'],
            'secret' => $validated['secret'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        AuditLog::log(
            action: 'create_webhook',
            description: "新增外部整合 Webhook「{$webhook->name}」",
            auditable: $webhook,
            details: ['url' => $webhook->url, 'events' => $webhook->events]
        );

        return redirect()->route('webhooks.index')->with('success', "已成功建立外部整合 Webhook「{$webhook->name}」！");
    }

    /**
     * 測試發送 Ping Payload
     */
    public function ping(Request $request, Webhook $webhook): RedirectResponse
    {
        if (!$request->user()->isAdmin()) {
            abort(403, '僅系統管理員具備操作權限。');
        }

        $testData = [
            'type' => 'test_ping',
            'sender' => $request->user()->name,
            'message' => '這是來自 EIP 企業入口網的 Webhook 連線測試訊號。',
        ];

        $success = WebhookService::send(
            $webhook,
            'system.ping',
            $testData,
            "【EIP 測試訊號】管理員 {$request->user()->name} 觸發了 Webhook 連線測試。"
        );

        if ($success) {
            return redirect()->route('webhooks.index')->with('success', "測試請求已成功送達「{$webhook->name}」（HTTP 2xx）！");
        }

        return redirect()->route('webhooks.index')->with('error', "發送至「{$webhook->name}」端點失敗，請檢查 URL 是否有效。");
    }

    /**
     * 切換啟用狀態
     */
    public function toggle(Request $request, Webhook $webhook): RedirectResponse
    {
        if (!$request->user()->isAdmin()) {
            abort(403, '僅系統管理員具備操作權限。');
        }

        $webhook->update([
            'is_active' => !$webhook->is_active,
        ]);

        $stateText = $webhook->is_active ? '啟用' : '停用';

        AuditLog::log(
            action: 'toggle_webhook',
            description: "{$stateText}了 Webhook「{$webhook->name}」",
            auditable: $webhook
        );

        return redirect()->route('webhooks.index')->with('success', "Webhook「{$webhook->name}」已{$stateText}。");
    }

    /**
     * 刪除 Webhook
     */
    public function destroy(Request $request, Webhook $webhook): RedirectResponse
    {
        if (!$request->user()->isAdmin()) {
            abort(403, '僅系統管理員具備操作權限。');
        }

        $name = $webhook->name;
        $webhook->delete();

        AuditLog::log(
            action: 'delete_webhook',
            description: "刪除了外部整合 Webhook「{$name}」"
        );

        return redirect()->route('webhooks.index')->with('success', "已刪除 Webhook「{$name}」。");
    }
}
