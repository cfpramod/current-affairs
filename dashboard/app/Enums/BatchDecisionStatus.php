<?php

declare(strict_types=1);

namespace App\Enums;

enum BatchDecisionStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Denied = 'denied';
}
