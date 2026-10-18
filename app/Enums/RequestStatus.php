<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Pending = 'Pending';
    case InProgress = 'In Progress';
    case ReadyForPickup = 'Ready for Pickup';
    case Completed = 'Completed';
    case Cancelled = 'Cancelled';

    /**
     * LEGACY ONLY. The rejection workflow was removed. This case exists solely so
     * rows imported from the old system (status = 'Rejected') can still be read.
     * New code must never assign it.
     */
    case Rejected = 'Rejected';

    /** Still being worked on (not finished, not cancelled, not legacy-rejected). */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::InProgress, self::ReadyForPickup], true);
    }

    /** Statuses that occupy a student's single request slot (unless archived). */
    public static function blocking(): array
    {
        return [self::Pending, self::InProgress, self::ReadyForPickup, self::Completed];
    }

    /** @return array<int, string> */
    public static function blockingValues(): array
    {
        return array_map(fn (self $status) => $status->value, self::blocking());
    }

    /** Statuses that appear in the read-only request history (legacy Rejected rows are history only). @return array<int, string> */
    public static function recordValues(): array
    {
        return [self::Completed->value, self::Rejected->value];
    }

    /** @return array<int, string> */
    public static function openValues(): array
    {
        return [self::Pending->value, self::InProgress->value, self::ReadyForPickup->value];
    }
}
