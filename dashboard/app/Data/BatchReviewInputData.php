<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class BatchReviewInputData extends Data
{
    public function __construct(
        public bool $accept,
        public string $reason,
        public ?string $nextAction,
        public bool $responsibilityConfirmed,
    ) {}
}
