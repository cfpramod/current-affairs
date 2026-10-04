<?php

declare(strict_types=1);

namespace App\Enums;

enum QaProspect: string
{
    case Unknown = 'unknown';
    case PotentiallyUsable = 'potentially_usable';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Unknown / checks pending',
            self::PotentiallyUsable => 'Potentially usable after checks',
            self::Rejected => 'Rejected for production',
        };
    }
}
