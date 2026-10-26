<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Departments/Index', [
            'departments' => Department::query()
                ->with(['manager'])
                ->withCount('users')
                ->orderBy('name')
                ->get()
                ->map(fn (Department $d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'manager_id' => $d->manager_id,
                    'manager_name' => $d->manager?->name,
                    'users_count' => $d->users_count,
                ]),
            'managers' => User::query()
                ->where('role', UserRole::Manager)
                ->orderBy('name')
                ->get(['id', 'name', 'department_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:departments,name'],
            'manager_id' => ['nullable', 'exists:users,id'],
        ]);

        Department::query()->create($data);

        return back()->with('success', 'Department created.');
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->ignore($department->id),
            ],
            'manager_id' => ['nullable', 'exists:users,id'],
        ]);

        $department->update($data);

        return back()->with('success', 'Department updated.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        if ($department->users()->exists() || $department->requisitions()->exists()) {
            return back()->withErrors([
                'department' => 'Cannot delete a department that still has users or requisitions.',
            ]);
        }

        $department->delete();

        return back()->with('success', 'Department deleted.');
    }
}
