<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $departments = Department::query()
            ->when($request->boolean('archived'), fn ($query) => $query->archived(), fn ($query) => $query->unarchived())
            ->withCount('users')
            ->when($request->filled('search'), fn ($query) => $query->where(function ($query) use ($request) {
                $search = '%'.$request->string('search')->trim()->toString().'%';
                $query->where('code', 'like', $search)->orWhere('name', 'like', $search);
            }))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.departments.index', compact('departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:departments,code'],
            'name' => ['required', 'string', 'max:100', 'unique:departments,name'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        Department::create($data);

        return back()->with('status', 'Department created.');
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        abort_if($department->archived_at !== null, 409, 'Restore the department before editing it.');

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('departments', 'code')->ignore($department->id)],
            'name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')->ignore($department->id)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        $department->update($data);

        return back()->with('status', 'Department updated.');
    }

    public function archive(Department $department): RedirectResponse
    {
        $department->archive();

        return back()->with('status', 'Department archived. Existing account records remain linked.');
    }

    public function restore(Department $department): RedirectResponse
    {
        $department->restoreArchive();

        return back()->with('status', 'Department restored.');
    }

    public function bulkArchive(Request $request): RedirectResponse
    {
        $departments = $this->selectedDepartments($request, false);
        DB::transaction(fn () => $departments->each(fn (Department $department) => $department->archive()));

        return back()->with('status', $departments->count().' department(s) archived.');
    }

    public function bulkRestore(Request $request): RedirectResponse
    {
        $departments = $this->selectedDepartments($request, true);
        DB::transaction(fn () => $departments->each(fn (Department $department) => $department->restoreArchive()));

        return back()->with('status', $departments->count().' department(s) restored.');
    }

    private function selectedDepartments(Request $request, bool $archived)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $query = Department::whereIn('id', $data['ids']);
        $archived ? $query->archived() : $query->unarchived();
        $departments = $query->get();
        if ($departments->count() !== count($data['ids'])) {
            throw ValidationException::withMessages(['ids' => 'Some selected departments are no longer available for this action.']);
        }

        return $departments;
    }
}
