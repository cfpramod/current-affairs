<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\ShipmentAction;
use Spatie\LaravelData\Data;

final class BatchDecisionInputData extends Data
{
    public function __construct(
        public string $assessmentId,
        public ShipmentAction $action,
        public string $reason,
    ) {}
}
