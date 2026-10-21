<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Department;
use App\Models\Office;
use App\Models\Position;
use App\Models\Section;
use App\Models\StudentYear;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $role = $request->query('role', 'all');
        if ($role !== 'all' && ! in_array($role, array_column(Role::cases(), 'value'), true)) {
            $role = 'all';
        }
        $users = User::query()
            ->when($request->boolean('archived'), fn ($query) => $query->archived(), fn ($query) => $query->unarchived())
            ->with(['department', 'office', 'positionRecord', 'courseRecord', 'yearRecord', 'sectionRecord'])
            ->when($role !== 'all', fn ($query) => $query->where('role', $role))
            ->when($request->filled('search'), fn ($query) => $query->where(function ($query) use ($request) {
                $search = '%'.$request->string('search')->trim()->toString().'%';
                $query->where('firstname', 'like', $search)
                    ->orWhere('surname', 'like', $search)
                    ->orWhere('school_id', 'like', $search)
                    ->orWhere('email', 'like', $search);
            }))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->orderBy('surname')
            ->paginate(15)
            ->withQueryString();

        $departments = Department::orderBy('name')->get();
        $offices = Office::orderBy('name')->get();
        $positions = Position::orderBy('name')->get();
        $courses = Course::with(['years' => fn ($query) => $query
            ->with(['sections' => fn ($sections) => $sections->orderBy('name')])
            ->orderBy('year_level')])
            ->orderBy('name')
            ->get();
        $years = StudentYear::with('course')->orderBy('year_level')->get();
        $sections = Section::with('studentYear')->orderBy('name')->get();
        $counts = [
            'total' => User::unarchived()->count(),
            'students' => User::unarchived()->where('role', Role::Student)->count(),
            'staff' => User::unarchived()->where('role', Role::Staff)->count(),
            'admins' => User::unarchived()->where('role', Role::Admin)->count(),
        ];

        $roleTabIndex = match ($role) {
            Role::Admin->value => 1,
            Role::Staff->value => 2,
            Role::Student->value => 3,
            default => 0,
        };
        $roleTabMobileIndex = match ($role) {
            Role::Admin->value, Role::Student->value => 1,
            default => 0,
        };

        return view('admin.users.index', compact(
            'users', 'departments', 'offices', 'positions', 'courses', 'years', 'sections',
            'counts', 'role', 'roleTabIndex', 'roleTabMobileIndex'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $data['name'] = trim($data['firstname'].' '.$data['surname']);
        $this->syncProfileValues($data);
        if ($data['role'] === Role::Student->value) {
            $data['department_id'] = null;
            $data['office_id'] = null;
            $data['position_id'] = null;
            $data['position'] = null;
        } else {
            $data['course_id'] = null;
            $data['student_year_id'] = null;
            $data['section_id'] = null;
            $data['course'] = null;
            $data['year'] = null;
            $data['section'] = null;
        }
        User::create($data);

        return back()->with('status', 'User account created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->archived_at !== null, 409, 'Restore the account before editing it.');

        if ($user->is($request->user()) && (
            $request->input('role') !== Role::Admin->value
            || $request->input('status') !== UserStatus::Activated->value
        )) {
            return back()->withErrors(['user' => 'You cannot deactivate or change the role of your own account.'])->withInput();
        }

        $data = $request->validate($this->rules($user));
        if ($user->hasRole(Role::Admin)
            && ($data['role'] !== Role::Admin->value || $data['status'] !== UserStatus::Activated->value)
            && User::unarchived()->where('role', Role::Admin)->where('status', UserStatus::Activated)->count() <= 1) {
            return back()->withErrors(['user' => 'At least one activated administrator account must remain.'])->withInput();
        }

        $data['name'] = trim($data['firstname'].' '.$data['surname']);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $this->syncProfileValues($data);
        if ($data['role'] === Role::Student->value) {
            $data['department_id'] = null;
            $data['office_id'] = null;
            $data['position_id'] = null;
            $data['position'] = null;
        } else {
            $data['course_id'] = null;
            $data['student_year_id'] = null;
            $data['section_id'] = null;
            $data['course'] = null;
            $data['year'] = null;
            $data['section'] = null;
        }
        $user->update($data);

        return back()->with('status', 'User account updated.');
    }

    public function archive(Request $request, User $user): RedirectResponse
    {
        $this->assertCanArchive($user, $request);
        $user->archive();

        return back()->with('status', 'User account archived. Its request and activity history has been preserved.');
    }

    public function restore(User $user): RedirectResponse
    {
        $user->restoreArchive();

        return back()->with('status', 'User account restored.');
    }

    public function bulkArchive(Request $request): RedirectResponse
    {
        $users = $this->selectedUsers($request, false);
        DB::transaction(function () use ($users, $request) {
            foreach ($users as $user) {
                $this->assertCanArchive($user, $request);
                $user->archive();
            }
        });

        return back()->with('status', $users->count().' user account(s) archived.');
    }

    public function bulkRestore(Request $request): RedirectResponse
    {
        $users = $this->selectedUsers($request, true);
        DB::transaction(fn () => $users->each(fn (User $user) => $user->restoreArchive()));

        return back()->with('status', $users->count().' user account(s) restored.');
    }

    private function selectedUsers(Request $request, bool $archived)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $query = User::whereIn('id', $data['ids']);
        $archived ? $query->archived() : $query->unarchived();
        $users = $query->get();
        if ($users->count() !== count($data['ids'])) {
            throw ValidationException::withMessages(['ids' => 'Some selected accounts are no longer available for this action.']);
        }

        return $users;
    }

    private function assertCanArchive(User $user, Request $request): void
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => 'You cannot archive your own account.']);
        }
        if ($user->documentRequests()->whereNotIn('status', [
            \App\Enums\RequestStatus::Completed->value,
            \App\Enums\RequestStatus::Rejected->value,
        ])->exists()) {
            throw ValidationException::withMessages(['user' => 'Accounts with open document requests cannot be archived.']);
        }
        if ($user->hasRole(Role::Admin)
            && $user->isActivated()
            && User::unarchived()->where('role', Role::Admin)->where('status', UserStatus::Activated)->count() <= 1) {
            throw ValidationException::withMessages(['user' => 'At least one unarchived, activated administrator account must remain.']);
        }
    }

    private function rules(?User $user = null): array
    {
        $role = request()->input('role', $user?->role?->value ?? Role::Student->value);
        $isStudent = $role === Role::Student->value;

        return [
            'firstname' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'school_id' => ['required', 'string', 'max:30', Rule::unique('users', 'school_id')->ignore($user?->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'department_id' => [$isStudent ? 'nullable' : 'required', 'integer', $this->activeOrAssigned('departments', $user?->department_id)],
            'office_id' => [$isStudent ? 'nullable' : 'required', 'integer', $this->activeOrAssigned('offices', $user?->office_id)],
            'position_id' => [$isStudent ? 'nullable' : 'required', 'integer', $this->activeOrAssigned('positions', $user?->position_id)],
            'role' => ['required', Rule::enum(Role::class)],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'course_id' => [$isStudent ? 'required' : 'nullable', 'integer', $this->activeOrAssigned('courses', $user?->course_id)],
            'student_year_id' => [
                $isStudent ? 'required' : 'nullable',
                'integer',
                Rule::exists('student_years', 'id')->where(fn ($query) => $query
                    ->where('course_id', request()->integer('course_id'))
                    ->where(fn ($active) => $active->whereNull('archived_at')->where('status', 'active')
                        ->when($user?->student_year_id, fn ($assigned) => $assigned->orWhere('id', $user->student_year_id)))),
            ],
            'section_id' => [
                $isStudent ? 'required' : 'nullable',
                'integer',
                Rule::exists('sections', 'id')->where(fn ($query) => $query
                    ->where('student_year_id', request()->integer('student_year_id'))
                    ->where(fn ($active) => $active->whereNull('archived_at')->where('status', 'active')
                        ->when($user?->section_id, fn ($assigned) => $assigned->orWhere('id', $user->section_id)))),
            ],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:12'],
        ];
    }

    private function activeOrAssigned(string $table, ?int $assignedId)
    {
        return Rule::exists($table, 'id')->where(fn ($query) => $query
            ->where(fn ($active) => $active->whereNull('archived_at')->where('status', 'active')
                ->when($assignedId, fn ($assigned) => $assigned->orWhere('id', $assignedId))));
    }

    private function syncProfileValues(array &$data): void
    {
        if (($data['role'] ?? null) === Role::Student->value) {
            $course = Course::find($data['course_id']);
            $year = StudentYear::where('course_id', $data['course_id'])->find($data['student_year_id']);
            $section = Section::where('student_year_id', $data['student_year_id'])->find($data['section_id']);
            if (! $course || ! $year || ! $section) {
                throw ValidationException::withMessages(['section_id' => 'Choose a year and section that belong to the selected course.']);
            }
            $data['course'] = $course->name;
            $data['year'] = $year->year_level;
            $data['section'] = $section->name;

            return;
        }

        $position = Position::find($data['position_id']);
        if (! $position || ! in_array($position->role, ['both', $data['role']], true)) {
            throw ValidationException::withMessages(['position_id' => 'Select a position assigned to this role.']);
        }
        $data['position'] = $position->name;
    }
}
