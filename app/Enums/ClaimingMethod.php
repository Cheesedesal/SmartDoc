<?php

namespace App\Enums;

enum ClaimingMethod: string
{
    case PickUp = 'Pick-up';

    /**
     * LEGACY ONLY. Email delivery was removed. Kept so imported rows that hold
     * 'Email' can still be read; it must never be offered or assigned.
     */
    case Email = 'Email';
}
