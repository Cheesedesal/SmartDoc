<?php

namespace App\Enums;

enum UserStatus: string
{
    case Activated = 'activated';
    case Pending = 'pending';
    case Deactivated = 'deactivated';
}
