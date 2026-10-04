<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\QaProspect;
use Spatie\LaravelData\Data;

final class BatchReviewInputData extends Data
{
    public function __construct(
        public bool $accept,
        public string $reason,
        public ?string $nextAction,
        public bool $responsibilityConfirmed,
        public ?QaProspect $qaProspect = null,
    ) {}
}
