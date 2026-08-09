<?php

namespace App\Enums;

enum DevicePlatform: string
{
    case Ios = 'ios';
    case Android = 'android';
    case Web = 'web';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
