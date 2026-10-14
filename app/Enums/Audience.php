<?php

namespace App\Enums;

enum Audience: string
{
    case Admin = 'admin';
    case Staff = 'staff';
    case Student = 'student';
    case All = 'all'; // legacy value: 'all users'
}
