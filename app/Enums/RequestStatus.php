<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Pending = 'Pending';
    case InProgress = 'In Progress';
    case ReadyForPickup = 'Ready for Pickup';
    case Completed = 'Completed';
    case Rejected = 'Rejected';

    /** Still on the staff work queue (legacy: NOT IN Completed, Rejected). */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Rejected], true);
    }

    /** Legacy rejectRequest(): not allowed once Completed, Rejected or Ready for Pickup. */
    public function canBeRejected(): bool
    {
        return ! in_array($this, [self::Completed, self::Rejected, self::ReadyForPickup], true);
    }
}
