<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $departmentId = $request->query('department_id');

        $departments = Department::withCount('users')
            ->with([
                'leader:id,name,email,job_title,employee_no',
                'users' => fn($q) => $q->select(['id', 'name', 'email', 'department_id', 'job_title', 'phone', 'employee_no', 'status', 'role'])
                    ->where('status', 'active')
                    ->orderBy('name'),
            ])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // 記憶體極速統計各部門總編制人數 (含所有子孫部門)
        $userCountMap = $departments->pluck('users_count', 'id')->toArray();
        $departments->each(function ($dept) use ($userCountMap) {
            $descendantIds = $dept->getAllDescendantIds();
            $total = ($userCountMap[$dept->id] ?? 0);
            foreach ($descendantIds as $childId) {
                $total += ($userCountMap[$childId] ?? 0);
            }
            $dept->total_headcount = $total;
        });

        $usersQuery = User::with('department:id,name,code')
            ->select(['id', 'name', 'email', 'department_id', 'job_title', 'phone', 'employee_no', 'status'])
            ->where('status', 'active');

        if ($departmentId) {
            $usersQuery->where('department_id', $departmentId);
        }
        if ($search) {
            $term = '%' . strtolower($search) . '%';
            $usersQuery->where(fn($q) => $q
                ->whereRaw('LOWER(name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                ->orWhereRaw('LOWER(job_title) LIKE ?', [$term])
            );
        }

        return Inertia::render('Directory/Index', [
            'departments' => $departments,
            'users' => $usersQuery->get(),
            'selectedDepartmentId' => $departmentId,
            'search' => $search ?? '',
        ]);
    }
}
