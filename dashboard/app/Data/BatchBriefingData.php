<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\BriefingSource;
use Spatie\LaravelData\Data;

final class BatchBriefingData extends Data
{
    public function __construct(
        public string $headline,
        public string $summary,
        public ?string $qaNote,
        public BriefingSource $source,
        public ?string $model,
    ) {}
}
