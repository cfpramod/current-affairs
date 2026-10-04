<?php

declare(strict_types=1);

namespace App\Actions\Batches;

use App\Exceptions\DataEngineUnavailable;
use App\Integrations\DataEngine;

final readonly class FindLatestBatchAssessment
{
    public function __construct(private DataEngine $engine) {}

    /**
     * The latest stored assessment for a batch, or null when none exists.
     *
     * @return ?array<string, mixed>
     *
     * @throws DataEngineUnavailable
     */
    public function handle(string $batchId): ?array
    {
        $known = collect($this->engine->batchAssessments())
            ->where('batch_id', $batchId)
            ->sortByDesc('as_of')
            ->first();

        return $known === null ? null : $this->engine->latestBatchAssessment((string) $known['dataset_id'], $batchId);
    }
}
