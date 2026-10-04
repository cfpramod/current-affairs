<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class BatchListItemData extends Data
{
    public function __construct(
        public string $batchId,
        public string $datasetId,
        public string $label,
        public ?string $reactor,
        public string $chargeAt,
        public string $asOf,
        public bool $retrospective,
        public AnswersData $answers,
        public BatchRecommendationData $recommendation,
        public ?BatchDecisionData $decision,
    ) {}
}
