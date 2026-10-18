<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AnnouncementStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnnouncementRequest;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Admin CRUD. The read-only feed for every role stays in App\Http\Controllers\AnnouncementController. */
class AnnouncementManagementController extends Controller
{
    public function index(Request $request): View
    {
        $this->expireOverdue();

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
        ];

        $query = Announcement::query()->with('author')->orderByDesc('id');

        if ($filters['q'] !== '') {
            $query->where('title', 'like', '%'.addcslashes($filters['q'], '%_\\').'%');
        }
        if (AnnouncementStatus::tryFrom($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $editing = ctype_digit((string) $request->query('edit')) ? Announcement::find((int) $request->query('edit')) : null;
        $viewing = ctype_digit((string) $request->query('view')) ? Announcement::with('author')->find((int) $request->query('view')) : null;

        return view('admin.announcements.index', [
            'announcements' => $query->paginate(10)->withQueryString(),
            'filters' => $filters,
            'editing' => $editing,
            'viewing' => $viewing,
            'counts' => [
                'total' => Announcement::count(),
                'published' => Announcement::where('status', AnnouncementStatus::Published->value)->count(),
                'draft' => Announcement::where('status', AnnouncementStatus::Draft->value)->count(),
                'expired' => Announcement::where('status', AnnouncementStatus::Expired->value)->count(),
            ],
        ]);
    }

    public function store(AnnouncementRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['published_at'] = $data['status'] === AnnouncementStatus::Published->value ? now() : null;

        Announcement::create($data);

        return redirect()->route('admin.announcements.index')->with('status', 'Announcement added successfully.');
    }

    public function update(AnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $data = $request->validated();

        // Same rule as legacy updateAnnouncement(): stamp published_at the first time it is published.
        if ($data['status'] === AnnouncementStatus::Published->value && $announcement->published_at === null) {
            $data['published_at'] = now();
        }

        $announcement->update($data);

        return redirect()->route('admin.announcements.index')->with('status', 'Announcement updated.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return redirect()->route('admin.announcements.index')->with('status', 'Announcement deleted.');
    }

    /** Port of legacy updateExpiredAnnouncements(). Visibility itself is already date-checked in Announcement::visibleTo(). */
    private function expireOverdue(): void
    {
        Announcement::query()
            ->where('status', AnnouncementStatus::Published->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now()->startOfDay())
            ->update(['status' => AnnouncementStatus::Expired->value]);
    }
}
