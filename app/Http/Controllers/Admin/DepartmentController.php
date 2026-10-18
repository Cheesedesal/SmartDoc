<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DepartmentRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $editing = ctype_digit((string) $request->query('edit')) ? Department::find((int) $request->query('edit')) : null;

        return view('admin.departments.index', [
            'departments' => Department::query()->withCount('users')->orderBy('id')->get(),
            'editing' => $editing,
        ]);
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        Department::create($request->validated());

        return redirect()->route('admin.departments.index')->with('status', 'Department added successfully.');
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());

        return redirect()->route('admin.departments.index')->with('status', 'Department updated.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        // The FK is nullOnDelete, so deleting would silently strip the department from its users.
        // Refuse instead; the admin can mark the department inactive.
        if ($department->users()->exists()) {
            return back()->withErrors(['request' => 'This department still has users assigned. Reassign them or set the department to inactive instead of deleting it.']);
        }

        $department->delete();

        return redirect()->route('admin.departments.index')->with('status', 'Department deleted.');
    }
}
