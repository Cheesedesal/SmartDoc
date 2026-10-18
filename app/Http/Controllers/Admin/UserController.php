<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'role' => (string) $request->query('role', ''),
            'status' => (string) $request->query('status', ''),
            'department_id' => (string) $request->query('department_id', ''),
        ];

        $query = User::query()->with('department')->orderBy('id');

        if ($filters['q'] !== '') {
            $like = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->where(fn ($w) => $w
                ->where('school_id', 'like', $like)
                ->orWhere('firstname', 'like', $like)
                ->orWhere('surname', 'like', $like)
                ->orWhere('email', 'like', $like));
        }
        if (Role::tryFrom($filters['role'])) {
            $query->where('role', $filters['role']);
        }
        if (UserStatus::tryFrom($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (ctype_digit($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }

        $editing = ctype_digit((string) $request->query('edit')) ? User::find((int) $request->query('edit')) : null;

        return view('admin.users.index', [
            'users' => $query->paginate(15)->withQueryString(),
            'filters' => $filters,
            'counts' => [
                'total' => User::count(),
                'students' => User::where('role', Role::Student->value)->count(),
                'staff' => User::where('role', Role::Staff->value)->count(),
                'admins' => User::where('role', Role::Admin->value)->count(),
            ],
            'departments' => Department::active()->get(),
            'editing' => $editing,
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['name'] = trim($data['firstname'].' '.$data['surname']); // users.name is NOT NULL in the base migration
        $data['department_id'] = $data['department_id'] ?? null;

        User::create($data); // 'password' is hashed by the model cast

        return redirect()->route('admin.users.index')->with('status', 'User added successfully.');
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $newRole = Role::from($data['role']);
        $newStatus = UserStatus::from($data['status']);

        if ($user->is($request->user()) && ($newRole !== Role::Admin || $newStatus !== UserStatus::Activated)) {
            return back()->withInput()->withErrors(['request' => 'You cannot remove your own administrator access or deactivate your own account.']);
        }

        if ($this->isLastActiveAdmin($user) && ($newRole !== Role::Admin || $newStatus !== UserStatus::Activated)) {
            return back()->withInput()->withErrors(['request' => 'This is the only active administrator. Create or activate another administrator first.']);
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $data['name'] = trim($data['firstname'].' '.$data['surname']);
        $data['department_id'] = $data['department_id'] ?? null;

        $user->update($data);

        return redirect()->route('admin.users.index')->with('status', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['request' => 'You cannot delete your own account.']);
        }

        if ($this->isLastActiveAdmin($user)) {
            return back()->withErrors(['request' => 'This is the only active administrator and cannot be deleted.']);
        }

        // document_requests.user_id is RESTRICT; announcements.created_by is CASCADE (would erase announcements).
        if (DocumentRequest::where('user_id', $user->id)->exists() || Announcement::where('created_by', $user->id)->exists()) {
            return back()->withErrors(['request' => 'This user has requests or announcements on record and cannot be deleted. Deactivate the account instead.']);
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'User deleted.');
    }

    private function isLastActiveAdmin(User $user): bool
    {
        return $user->role === Role::Admin
            && $user->status === UserStatus::Activated
            && ! User::where('role', Role::Admin->value)
                ->where('status', UserStatus::Activated->value)
                ->whereKeyNot($user->id)
                ->exists();
    }
}
