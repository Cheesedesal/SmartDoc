<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $role = $user->role;

        $requests = DocumentRequest::query()
            ->with(['student', 'documentType'])
            ->latest()
            ->limit(6);

        if ($role === Role::Student) {
            $requests->where('user_id', $user->id);
        } else {
            $requests->whereNotIn('status', [
                RequestStatus::Completed->value,
                RequestStatus::Rejected->value,
            ]);
        }

        $announcements = Announcement::visibleTo($role)
            ->limit(4)
            ->get();

        $data = [
            'user' => $user,
            'role' => $role,
            'requestCounts' => $this->requestCounts(
                $role === Role::Student
                    ? fn (Builder $query) => $query->where('user_id', $user->id)
                    : null
            ),
            'recentRequests' => $requests->get(),
            'announcements' => $announcements,
        ];

        if ($role === Role::Admin) {
            $data['adminStats'] = [
                'total_users' => User::count(),
                'students' => User::where('role', Role::Student->value)->count(),
                'staff' => User::where('role', Role::Staff->value)->count(),
                'active_users' => User::where('status', 'activated')->count(),
                'departments' => Department::count(),
                'document_types' => DocumentType::count(),
            ];
        }

        if ($role === Role::Student) {
            $data['availableDocumentTypes'] = DocumentType::active()->count();
        }

        return view('dashboard', $data);
    }

    private function requestCounts(?callable $scope = null): array
    {
        $query = DocumentRequest::query();

        if ($scope !== null) {
            $scope($query);
        }

        $counts = $query
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'total' => $counts->sum(),
            'pending' => (int) ($counts[RequestStatus::Pending->value] ?? 0),
            'in_progress' => (int) ($counts[RequestStatus::InProgress->value] ?? 0),
            'ready_for_pickup' => (int) ($counts[RequestStatus::ReadyForPickup->value] ?? 0),
            'completed' => (int) ($counts[RequestStatus::Completed->value] ?? 0),
            'rejected' => (int) ($counts[RequestStatus::Rejected->value] ?? 0),
        ];
    }
}
