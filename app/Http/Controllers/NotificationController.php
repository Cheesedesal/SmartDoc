<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()
            ->latest()
            ->limit(10)
            ->get()
            ->map(static fn ($notification) => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'SmartDoc update',
                'message' => $notification->data['message'] ?? '',
                'url' => $notification->data['url'] ?? route('dashboard'),
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at->diffForHumans(),
            ]);

        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'Notifications marked as read.']);
    }
}
