<?php

declare(strict_types=1);

namespace App\Enums;

enum DecisionLane: string
{
    case Material = 'material';
    case Transport = 'transport';

    public function label(): string
    {
        return match ($this) {
            self::Material => 'Material disposition',
            self::Transport => 'Transport',
        };
    }
}
