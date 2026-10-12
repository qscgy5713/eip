<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Services\Branding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BrandingController extends Controller
{
    /** 品牌設定頁 (管理員專屬) */
    public function edit(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return Inertia::render('Branding/Edit', [
            'defaultName' => Branding::DEFAULT_NAME,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,gif', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico', 'max:512'],
            'remove_logo' => ['boolean'],
            'remove_favicon' => ['boolean'],
        ]);

        $changes = [];

        if (trim($data['name']) !== Branding::name()) {
            SystemSetting::set('brand_name', trim($data['name']), '網站名稱 / 瀏覽器標題');
            $changes[] = '網站名稱';
        }

        foreach (Branding::KINDS as $kind) {
            if ($request->hasFile($kind)) {
                $this->deleteFile($kind);
                $path = $request->file($kind)->store("branding", 'public');
                SystemSetting::set("brand_{$kind}_path", $path, $kind === 'logo' ? '網站 Logo 檔案' : '網站 Favicon 檔案');
                $changes[] = $kind === 'logo' ? 'Logo' : 'Favicon';
            } elseif ($request->boolean("remove_{$kind}") && Branding::path($kind)) {
                $this->deleteFile($kind);
                SystemSetting::forget("brand_{$kind}_path");
                $changes[] = ($kind === 'logo' ? 'Logo' : 'Favicon').'(還原預設)';
            }
        }

        if ($changes) {
            AuditLog::log('update_branding', '更新品牌設定:'.implode('、', $changes), null, ['changes' => $changes]);
        }

        return redirect()->route('branding.edit')->with('success', $changes ? '品牌設定已更新。' : '沒有任何變更。');
    }

    /** 提供 Logo / Favicon 檔案 (登入頁也要用,故不需登入) */
    public function asset(string $kind): StreamedResponse
    {
        abort_unless(in_array($kind, Branding::KINDS, true), 404);
        $path = Branding::path($kind);
        abort_unless($path, 404);

        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function deleteFile(string $kind): void
    {
        if ($path = Branding::path($kind)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403, '僅系統管理員可變更品牌設定。');
    }
}
