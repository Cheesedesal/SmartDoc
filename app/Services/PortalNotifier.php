<?php

namespace App\Services;

use App\Enums\Audience;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\User;
use App\Notifications\PortalNotification;
use Illuminate\Support\Facades\Notification;

class PortalNotifier
{
    public function newRequest(DocumentRequest $request): void
    {
        $users = User::whereIn('role', [Role::Staff->value, Role::Admin->value])
            ->unarchived()
            ->where('status', UserStatus::Activated->value)
            ->get();
        Notification::send($users, new PortalNotification(
            'New document request',
            $request->reference.' · '.$request->student->full_name.' requested '.$request->documentType->name,
            route('request-management.show', $request),
            'new_request'
        ));
    }

    public function requestUpdated(DocumentRequest $request, string $status): void
    {
        $request->student->notify(new PortalNotification(
            'Request status updated',
            $request->reference.' · '.$request->documentType->name.': '.$status,
            route('requests.show', $request),
            'request_status'
        ));
    }

    public function announcementPublished(Announcement $announcement): void
    {
        $roles = $announcement->audience === Audience::All
            ? array_column(Role::cases(), 'value')
            : [$announcement->audience->value];
        $users = User::whereIn('role', $roles)
            ->unarchived()
            ->where('status', UserStatus::Activated->value)
            ->get();

        Notification::send($users, new PortalNotification(
            'New announcement',
            $announcement->title,
            route('announcements.index'),
            'announcement'
        ));
    }

    public function documentTypeActivated(DocumentType $documentType): void
    {
        $users = User::where('role', Role::Student->value)
            ->unarchived()
            ->where('status', UserStatus::Activated->value)
            ->get();

        Notification::send($users, new PortalNotification(
            'New document available',
            $documentType->name.' can now be requested.',
            route('requests.index'),
            'document_type'
        ));
    }
}
