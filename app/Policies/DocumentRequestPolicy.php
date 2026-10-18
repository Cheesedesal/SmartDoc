<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\DocumentRequest;
use App\Models\User;

/**
 * Auto-discovered by Laravel (App\Models\DocumentRequest -> App\Policies\DocumentRequestPolicy).
 *
 * view    : the owning student, or staff/admin (read-only monitoring).
 * manage  : ONLY the owning student (cancel, resubmit payment, confirm receipt, archive).
 *           Staff and admins are deliberately excluded: they cannot act as a student.
 */
class DocumentRequestPolicy
{
    public function view(User $user, DocumentRequest $documentRequest): bool
    {
        return $this->owns($user, $documentRequest) || $user->hasRole(Role::Staff, Role::Admin);
    }

    public function manage(User $user, DocumentRequest $documentRequest): bool
    {
        return $user->hasRole(Role::Student) && $this->owns($user, $documentRequest);
    }

    private function owns(User $user, DocumentRequest $documentRequest): bool
    {
        return (int) $documentRequest->user_id === (int) $user->id;
    }
}
