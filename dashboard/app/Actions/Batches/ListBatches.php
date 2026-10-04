<?php

declare(strict_types=1);

namespace App\Actions\Batches;

use App\Data\BatchDecisionData;
use App\Data\BatchListItemData;
use App\Data\BatchRecommendationData;
use App\Exceptions\DataEngineUnavailable;
use App\Integrations\DataEngine;
use App\Models\BatchDecision;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final readonly class ListBatches
{
    public function __construct(
        private DataEngine $engine,
        private SummarizeBatchAssessment $summarize,
    ) {}

    public static function label(string $batchId): string
    {
        return preg_match('/batch-(\d+)$/', $batchId, $match) === 1 ? 'Batch '.$match[1] : Str::headline($batchId);
    }

    /**
     * Batches with a stored assessment, soonest charge first.
     *
     * @return list<BatchListItemData>
     *
     * @throws DataEngineUnavailable
     */
    public function handle(): array
    {
        $reactors = $this->reactors();
        $latest = collect($this->engine->batchAssessments())
            ->sortByDesc('as_of')
            ->unique('batch_id');
        $decisions = BatchDecision::query()
            ->with(['submitter', 'reviewer'])
            ->whereIn('batch_id', $latest->pluck('batch_id'))
            ->latest('submitted_at')
            ->get()
            ->unique('batch_id')
            ->keyBy('batch_id');

        return array_values($latest
            ->map(function (array $assessment) use ($reactors, $decisions): BatchListItemData {
                $chargeAt = (string) $assessment['production_readiness']['planned_charge_at'];
                $decision = $decisions->get($assessment['batch_id']);

                return new BatchListItemData(
                    batchId: $assessment['batch_id'],
                    datasetId: $assessment['dataset_id'],
                    label: self::label($assessment['batch_id']),
                    reactor: $reactors[$assessment['batch_id']] ?? null,
                    chargeAt: $chargeAt,
                    asOf: (string) $assessment['as_of'],
                    retrospective: CarbonImmutable::parse($chargeAt)->lt(CarbonImmutable::parse($assessment['as_of'])),
                    answers: $this->summarize->handle($assessment),
                    recommendation: BatchRecommendationData::fromEngine($assessment['recommendation']),
                    decision: $decision === null ? null : BatchDecisionData::fromModel($decision),
                );
            })
            ->sortBy('chargeAt')
            ->all());
    }

    /**
     * @return array<string, string>
     *
     * @throws DataEngineUnavailable
     */
    private function reactors(): array
    {
        $reactors = [];

        foreach ($this->engine->operations() as $dataset) {
            /** @var list<array{id: string, reactor: string}> $batches */
            $batches = $dataset['batches'] ?? [];
            foreach ($batches as $batch) {
                $reactors[$batch['id']] = $batch['reactor'];
            }
        }

        return $reactors;
    }
}
