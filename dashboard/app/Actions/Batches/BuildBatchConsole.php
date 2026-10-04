<?php

declare(strict_types=1);

namespace App\Actions\Batches;

use App\Data\BatchConsoleData;
use App\Data\BatchDecisionData;
use App\Data\BatchRecommendationData;
use App\Enums\BatchDecisionStatus;
use App\Exceptions\DataEngineUnavailable;
use App\Integrations\DataEngine;
use App\Models\BatchDecision;
use Carbon\CarbonImmutable;

final readonly class BuildBatchConsole
{
    public function __construct(
        private DataEngine $engine,
        private FindLatestBatchAssessment $findLatest,
        private SummarizeBatchAssessment $summarize,
        private BriefBatch $brief,
    ) {}

    /**
     * Build the console for a batch, or null when no assessment is stored for it.
     *
     * @throws DataEngineUnavailable
     */
    public function handle(string $batchId): ?BatchConsoleData
    {
        $assessment = $this->findLatest->handle($batchId);

        if ($assessment === null) {
            return null;
        }

        $dataset = collect($this->engine->operations())->firstWhere('dataset_id', $assessment['dataset_id']) ?? [];
        /** @var list<array{id: string, reactor: string}> $batches */
        $batches = $dataset['batches'] ?? [];
        $batch = array_values(array_filter($batches, fn (array $candidate): bool => $candidate['id'] === $batchId))[0] ?? null;
        $chargeAt = (string) $assessment['production_readiness']['planned_charge_at'];
        $decisions = BatchDecision::query()
            ->with(['submitter', 'reviewer'])
            ->where('batch_id', $batchId)
            ->latest('submitted_at')
            ->get();
        $active = $decisions->first(fn (BatchDecision $decision): bool => $decision->status !== BatchDecisionStatus::Denied);

        return new BatchConsoleData(
            batchId: $batchId,
            datasetId: (string) $assessment['dataset_id'],
            label: ListBatches::label($batchId),
            reactor: $batch['reactor'] ?? null,
            chargeAt: $chargeAt,
            asOf: (string) $assessment['as_of'],
            assessmentId: (string) $assessment['assessment_id'],
            retrospective: CarbonImmutable::parse($chargeAt)->lt(CarbonImmutable::parse($assessment['as_of'])),
            answers: $this->summarize->handle($assessment),
            recommendation: BatchRecommendationData::fromEngine($assessment['recommendation']),
            production: $assessment['production_readiness'],
            incoming: array_values($assessment['incoming_shipments']),
            temperatures: array_values($assessment['product_temperature']),
            temperatureSeries: $this->temperatureSeries($dataset['readings'] ?? [], $assessment),
            limitations: array_values($assessment['limitations']),
            assessment: $assessment,
            decisions: array_values($decisions->map(BatchDecisionData::fromModel(...))->all()),
            activeDecision: $active === null ? null : BatchDecisionData::fromModel($active),
            briefing: $this->brief->handle($assessment),
        );
    }

    /**
     * Product readings up to the assessment cutoff, per supplying shipment.
     *
     * @param  list<array<string, mixed>>  $readings
     * @param  array<string, mixed>  $assessment
     * @return list<array{shipmentId: string, points: list<array{at: string, c: float}>, exposures: list<array{from: string, to: string, label: string}>}>
     */
    private function temperatureSeries(array $readings, array $assessment): array
    {
        $cutoff = CarbonImmutable::parse($assessment['as_of']);
        $series = [];

        foreach ($assessment['shipment_ids'] as $shipmentId) {
            $shipmentReadings = array_values(array_filter(
                $readings,
                fn (array $reading): bool => $reading['shipment_id'] === $shipmentId && CarbonImmutable::parse($reading['at'])->lte($cutoff),
            ));

            if ($shipmentReadings === []) {
                continue;
            }

            usort($shipmentReadings, fn (array $a, array $b): int => strcmp((string) $a['at'], (string) $b['at']));

            $series[] = [
                'shipmentId' => (string) $shipmentId,
                'points' => array_map(fn (array $r): array => ['at' => (string) $r['at'], 'c' => (float) $r['product_c']], $shipmentReadings),
                'exposures' => $this->exposures($shipmentReadings),
            ];
        }

        return $series;
    }

    /**
     * Consecutive unrefrigerated readings, shown as exposed handovers.
     *
     * @param  list<array<string, mixed>>  $readings
     * @return list<array{from: string, to: string, label: string}>
     */
    private function exposures(array $readings): array
    {
        $exposures = [];
        $run = [];

        foreach ([...$readings, null] as $reading) {
            if ($reading !== null && ! $reading['refrigerated']) {
                $run[] = (string) $reading['at'];

                continue;
            }

            if ($run !== []) {
                $exposures[] = ['from' => $run[0], 'to' => $run[array_key_last($run)], 'label' => 'Unrefrigerated'];
                $run = [];
            }
        }

        return $exposures;
    }
}
