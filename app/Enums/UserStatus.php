<?php

namespace App\Enums;

enum UserStatus: string
{
    case Activated = 'activated';
    case Deactivated = 'deactivated';
}
