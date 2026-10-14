<?php

namespace App\Enums;

enum AnnouncementStatus: string
{
    case Published = 'Published';
    case Draft = 'Draft';
    case Expired = 'Expired';
}
