<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only announcement feed for every authenticated role (route group: `auth`).
 *
 * Visibility is NOT re-implemented here: Announcement::visibleTo() is the single
 * source of truth (Published only, audience = the user's role or "all", not expired),
 * the same scope the dashboard preview uses.
 */
class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $role = $request->user()->role;

        $announcements = Announcement::visibleTo($role)
            ->with('author')
            ->orderByDesc('id') // stable tie-break after the scope's latest('published_at')
            ->paginate(10);

        return view('announcements.index', [
            'announcements' => $announcements,
        ]);
    }
}
