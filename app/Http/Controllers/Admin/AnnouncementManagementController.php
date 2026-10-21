<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AnnouncementStatus;
use App\Enums\Audience;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\PortalNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AnnouncementManagementController extends Controller
{
    public function index(Request $request): View
    {
        Announcement::unarchived()->where('status', AnnouncementStatus::Published)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => AnnouncementStatus::Expired->value]);

        $announcements = Announcement::query()
            ->when($request->boolean('archived'), fn ($query) => $query->archived(), fn ($query) => $query->unarchived())
            ->with('author')
            ->when($request->filled('search'), fn ($query) => $query->where('title', 'like', '%'.$request->string('search')->trim()->toString().'%'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();
        $counts = [
            'total' => Announcement::unarchived()->count(),
            'published' => Announcement::unarchived()->where('status', AnnouncementStatus::Published)->count(),
            'draft' => Announcement::unarchived()->where('status', AnnouncementStatus::Draft)->count(),
            'expired' => Announcement::unarchived()->where('status', AnnouncementStatus::Expired)->count(),
        ];

        return view('admin.announcements.index', compact('announcements', 'counts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $data['created_by'] = $request->user()->id;
        $data['published_at'] = $data['status'] === AnnouncementStatus::Published->value ? now() : null;
        $data['expires_at'] = ! empty($data['expires_at']) ? Carbon::parse($data['expires_at'])->endOfDay() : null;
        $announcement = Announcement::create($data);
        if ($announcement->status === AnnouncementStatus::Published) {
            app(PortalNotifier::class)->announcementPublished($announcement);
        }

        return back()->with('status', 'Announcement created.');
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_if($announcement->archived_at !== null, 409, 'Restore the announcement before editing it.');

        $wasPublished = $announcement->status === AnnouncementStatus::Published;
        $data = $request->validate($this->rules($announcement));
        if ($data['status'] === AnnouncementStatus::Published->value) {
            $data['published_at'] = $announcement->published_at ?? now();
        }
        $data['expires_at'] = ! empty($data['expires_at']) ? Carbon::parse($data['expires_at'])->endOfDay() : null;
        $announcement->update($data);
        if (! $wasPublished && $announcement->status === AnnouncementStatus::Published) {
            app(PortalNotifier::class)->announcementPublished($announcement);
        }

        return back()->with('status', 'Announcement updated.');
    }

    public function archive(Announcement $announcement): RedirectResponse
    {
        $announcement->archive();

        return back()->with('status', 'Announcement archived.');
    }

    public function restore(Announcement $announcement): RedirectResponse
    {
        $announcement->restoreArchive();

        return back()->with('status', 'Announcement restored.');
    }

    public function bulkArchive(Request $request): RedirectResponse
    {
        $announcements = $this->selectedAnnouncements($request, false);
        DB::transaction(fn () => $announcements->each(fn (Announcement $announcement) => $announcement->archive()));

        return back()->with('status', $announcements->count().' announcement(s) archived.');
    }

    public function bulkRestore(Request $request): RedirectResponse
    {
        $announcements = $this->selectedAnnouncements($request, true);
        DB::transaction(fn () => $announcements->each(fn (Announcement $announcement) => $announcement->restoreArchive()));

        return back()->with('status', $announcements->count().' announcement(s) restored.');
    }

    private function selectedAnnouncements(Request $request, bool $archived)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $query = Announcement::whereIn('id', $data['ids']);
        $archived ? $query->archived() : $query->unarchived();
        $announcements = $query->get();
        if ($announcements->count() !== count($data['ids'])) {
            throw ValidationException::withMessages(['ids' => 'Some selected announcements are no longer available for this action.']);
        }

        return $announcements;
    }

    private function rules(?Announcement $announcement = null): array
    {
        return [
            'title' => ['required', 'string', 'max:200', Rule::unique('announcements', 'title')->ignore($announcement?->id)],
            'message' => ['required', 'string', 'max:20000'],
            'audience' => ['required', Rule::enum(Audience::class)],
            'status' => ['required', Rule::in([AnnouncementStatus::Draft->value, AnnouncementStatus::Published->value])],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
