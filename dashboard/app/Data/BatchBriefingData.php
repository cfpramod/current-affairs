<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class BatchBriefingData extends Data
{
    /**
     * @param  'llm'|'template'  $source
     */
    public function __construct(
        public string $headline,
        public string $summary,
        public ?string $qaNote,
        public string $source,
        public ?string $model,
    ) {}
}
