<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\QaProspect;
use Spatie\LaravelData\Data;

/**
 * Everything the batch console renders: the stored engine evidence plus the
 * decisions people recorded against it.
 */
final class BatchConsoleData extends Data
{
    /**
     * @param  array<string, mixed>  $production
     * @param  list<array<string, mixed>>  $incoming
     * @param  list<array<string, mixed>>  $temperatures
     * @param  list<array{shipmentId: string, points: list<array{at: string, c: float}>, exposures: list<array{from: string, to: string, label: string}>}>  $temperatureSeries
     * @param  list<string>  $limitations
     * @param  array<string, mixed>  $assessment
     * @param  list<BatchDecisionData>  $decisions
     */
    public function __construct(
        public string $batchId,
        public string $datasetId,
        public string $label,
        public ?string $reactor,
        public string $chargeAt,
        public string $asOf,
        public string $assessmentId,
        public bool $retrospective,
        public AnswersData $answers,
        public BatchRecommendationData $recommendation,
        public array $production,
        public array $incoming,
        public array $temperatures,
        public array $temperatureSeries,
        public array $limitations,
        public array $assessment,
        public array $decisions,
        public ?BatchDecisionData $activeDecision,
        public BatchBriefingData $briefing,
        public ?BatchDecisionData $activeMaterialDecision = null,
        public ?BatchDecisionData $activeTransportDecision = null,
        public ?QaProspect $qaProspect = null,
        public bool $materialHeld = false,
        public bool $expediteAllowed = false,
        public ?string $expediteBlockedReason = null,
        public bool $rerouteAllowed = false,
        public ?string $rerouteBlockedReason = null,
        public bool $bufferAllowed = true,
        public ?string $bufferBlockedReason = null,
        public bool $runAllowed = true,
        public ?string $runBlockedReason = null,
    ) {}
}
