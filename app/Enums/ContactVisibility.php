<?php

namespace App\Enums;

enum ContactVisibility: string
{
    case Hidden = 'hidden';
    case Connections = 'connections';
    case Everyone = 'everyone';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
