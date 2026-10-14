<?php

namespace App\Enums;

enum Role: string
{
    case Student = 'student';
    case Staff = 'staff';
    case Admin = 'admin';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
