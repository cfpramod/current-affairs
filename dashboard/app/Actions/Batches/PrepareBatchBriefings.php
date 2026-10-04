<?php

declare(strict_types=1);

namespace App\Actions\Batches;

use App\Enums\BriefingSource;
use App\Exceptions\DataEngineUnavailable;
use App\Integrations\DataEngine;

final readonly class PrepareBatchBriefings
{
    public function __construct(
        private DataEngine $engine,
        private BriefBatch $brief,
    ) {}

    /**
     * Brief the latest assessment of every batch ahead of time, so no page
     * load waits on the provider. Returns each batch's briefing source.
     *
     * @return array<string, BriefingSource>
     *
     * @throws DataEngineUnavailable
     */
    public function handle(): array
    {
        $sources = [];

        foreach (collect($this->engine->batchAssessments())->sortByDesc('as_of')->unique('batch_id') as $assessment) {
            $sources[(string) $assessment['batch_id']] = $this->brief->handle($assessment)->source;
        }

        ksort($sources);

        return $sources;
    }
}
