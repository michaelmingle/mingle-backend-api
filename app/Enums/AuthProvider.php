<?php

namespace App\Enums;

enum AuthProvider: string
{
    case Google = 'google';
    case Apple = 'apple';
    case Email = 'email';
    case Phone = 'phone';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
