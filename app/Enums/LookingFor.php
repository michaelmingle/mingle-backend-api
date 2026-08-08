<?php

namespace App\Enums;

enum LookingFor: string
{
    case Networking = 'networking';
    case Business = 'business';
    case Employment = 'employment';
    case Mentorship = 'mentorship';
    case Clients = 'clients';
    case Collaboration = 'collaboration';
    case Friends = 'friends';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
