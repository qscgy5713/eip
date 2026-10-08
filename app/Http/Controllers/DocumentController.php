<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /**
     * 文件庫首頁與分類列表
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $category = $request->input('category');
        $search = $request->input('search');
        $deptId = $request->input('department_id');

        $query = Document::query()
            ->with(['latestVersion.uploader', 'uploader', 'department'])
            ->withCount('versions')
            ->latest();

        // 權限過濾：非管理員僅能檢視無限制或包含其角色的文件
        if (!$user->isAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('restricted_roles')
                  ->orWhereJsonContains('restricted_roles', $user->role);
            });
        }

        // 分類過濾
        if ($category && $category !== 'all') {
            $query->where('category', $category);
        }

        // 部門過濾
        if ($deptId) {
            $query->where('department_id', $deptId);
        }

        // 關鍵字搜尋 (標題與說明)
        if ($search) {
            $term = '%' . strtolower(trim($search)) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(title) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(description) LIKE ?', [$term]);
            });
        }

        $documents = $query->paginate(12)->withQueryString();
        $departments = Department::orderBy('sort_order')->get(['id', 'name']);

        return Inertia::render('Documents/Index', [
            'documents' => $documents,
            'departments' => $departments,
            'filters' => [
                'category' => $category ?? 'all',
                'search' => $search ?? '',
                'department_id' => $deptId ?? '',
            ],
            'canManage' => $user->isAdmin() || $user->isManager(),
        ]);
    }

    /**
     * 上傳新文件
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', 'in:policy,template,tech,training'],
            'description' => ['nullable', 'string', 'max:1000'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'restricted_roles' => ['nullable', 'array'],
            'restricted_roles.*' => ['string', 'in:admin,manager,employee,hr'],
            'version_label' => ['nullable', 'string', 'max:20'],
            'changelog' => ['nullable', 'string', 'max:500'],
            'file' => ['required', 'file', 'max:51200'], // 最大 50MB
        ]);

        $uploadedFile = $request->file('file');
        $storedPath = $uploadedFile->store('documents');

        $document = Document::create([
            'title' => $validated['title'],
            'category' => $validated['category'],
            'description' => $validated['description'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'uploader_id' => $request->user()->id,
            'current_version' => 1,
            'download_count' => 0,
            'restricted_roles' => !empty($validated['restricted_roles']) ? array_values($validated['restricted_roles']) : null,
        ]);

        DocumentVersion::create([
            'document_id' => $document->id,
            'uploader_id' => $request->user()->id,
            'version_number' => 1,
            'version_label' => $validated['version_label'] ?? 'v1.0',
            'file_path' => $storedPath,
            'file_name' => $uploadedFile->getClientOriginalName(),
            'file_size' => $uploadedFile->getSize(),
            'mime_type' => $uploadedFile->getClientMimeType(),
            'changelog' => $validated['changelog'] ?? '初版文件建立上傳',
        ]);

        AuditLog::log(
            action: 'upload_document',
            description: "上傳了新企業文件「{$document->title}」",
            auditable: $document,
            details: ['category' => $document->category, 'file_name' => $uploadedFile->getClientOriginalName()]
        );

        return back()->with('success', "文件「{$document->title}」已成功上傳！");
    }

    /**
     * 上傳新修訂版本
     */
    public function uploadVersion(Request $request, Document $document): RedirectResponse
    {
        // 只有原上傳者、主管或管理員可更新版本
        if ($document->uploader_id !== $request->user()->id && !$request->user()->isManager()) {
            abort(403, '您沒有權限為此文件發布新版本。');
        }

        $validated = $request->validate([
            'version_label' => ['required', 'string', 'max:20'],
            'changelog' => ['nullable', 'string', 'max:500'],
            'file' => ['required', 'file', 'max:51200'],
        ]);

        $uploadedFile = $request->file('file');
        $storedPath = $uploadedFile->store('documents');

        $nextVersionNumber = $document->versions()->max('version_number') + 1;

        DocumentVersion::create([
            'document_id' => $document->id,
            'uploader_id' => $request->user()->id,
            'version_number' => $nextVersionNumber,
            'version_label' => $validated['version_label'],
            'file_path' => $storedPath,
            'file_name' => $uploadedFile->getClientOriginalName(),
            'file_size' => $uploadedFile->getSize(),
            'mime_type' => $uploadedFile->getClientMimeType(),
            'changelog' => $validated['changelog'] ?? "更新修訂至 {$validated['version_label']}",
        ]);

        $document->update([
            'current_version' => $nextVersionNumber,
        ]);

        AuditLog::log(
            action: 'upload_document_version',
            description: "為文件「{$document->title}」發布了新版本 {$validated['version_label']}",
            auditable: $document,
            details: ['version_number' => $nextVersionNumber, 'version_label' => $validated['version_label']]
        );

        return back()->with('success', "文件「{$document->title}」新版本 {$validated['version_label']} 已成功發布！");
    }

    /**
     * 取得文件完整歷史版本清單 (API / Modal 檢視)
     */
    public function versions(Document $document)
    {
        if (!$document->canAccess(auth()->user())) {
            abort(403, '您沒有權限檢閱此文件之版本記錄。');
        }

        $versions = $document->versions()
            ->with('uploader:id,name,email')
            ->get();

        return response()->json([
            'document' => $document->only(['id', 'title', 'category', 'current_version']),
            'versions' => $versions,
        ]);
    }

    /**
     * 安全下載檔案 (支援最新或指定歷史版本)
     */
    public function download(Request $request, Document $document, ?DocumentVersion $version = null): StreamedResponse
    {
        if (!$document->canAccess($request->user())) {
            abort(403, '您沒有權限下載此機密文件。');
        }

        $targetVersion = $version ?? $document->latestVersion;

        if (!$targetVersion || !Storage::exists($targetVersion->file_path)) {
            abort(404, '文件實體檔案不存在或已被移除。');
        }

        $document->increment('download_count');

        AuditLog::log(
            action: 'download_document',
            description: "下載了企業文件「{$document->title}」({$targetVersion->version_label})",
            auditable: $document,
            details: ['version_label' => $targetVersion->version_label, 'file_name' => $targetVersion->file_name]
        );

        return Storage::download($targetVersion->file_path, $targetVersion->file_name);
    }

    /**
     * 刪除文件與實體檔案
     */
    public function destroy(Request $request, Document $document): RedirectResponse
    {
        if ($document->uploader_id !== $request->user()->id && !$request->user()->isAdmin()) {
            abort(403, '僅原上傳者或系統管理員可以刪除此文件。');
        }

        // 刪除所有版本在磁碟上的檔案
        foreach ($document->versions as $ver) {
            if (Storage::exists($ver->file_path)) {
                Storage::delete($ver->file_path);
            }
        }

        $title = $document->title;
        $document->delete();

        AuditLog::log(
            action: 'delete_document',
            description: "刪除了文件「{$title}」及其歷史版本"
        );

        return back()->with('success', "已刪除文件「{$title}」及其所有版本記錄。");
    }
}
