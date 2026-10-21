<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $announcements = Announcement::visibleTo($request->user()->role)
            ->with('author')
            ->paginate(10);

        return view('announcements.index', compact('announcements'));
    }
}
