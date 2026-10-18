<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case GCash = 'GCash';
    case Maya = 'Maya';
    case Card = 'Credit/Debit Card';
    case OnlineBanking = 'Online Banking';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $method) => $method->value, self::cases());
    }
}
