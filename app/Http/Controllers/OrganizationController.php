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

        $departments = Department::withCount('users')->orderBy('sort_order')->get();

        $usersQuery = User::with('department')->where('status', 'active');

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
