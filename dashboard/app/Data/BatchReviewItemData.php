<?php

declare(strict_types=1);

namespace App\Data;

use App\Actions\Batches\ListBatches;
use App\Enums\ShipmentAction;
use App\Models\BatchDecision;
use Spatie\LaravelData\Data;

final class BatchReviewItemData extends Data
{
    public function __construct(
        public int $id,
        public string $batchId,
        public string $label,
        public ShipmentAction $action,
        public ?ShipmentAction $modelAction,
        public string $submittedBy,
        public string $submittedReason,
        public string $submittedAt,
        public ?string $chargeAt,
    ) {}

    public static function fromModel(BatchDecision $decision): self
    {
        $chargeAt = $decision->assessment['production_readiness']['planned_charge_at'] ?? null;

        return new self(
            id: $decision->id,
            batchId: $decision->batch_id,
            label: ListBatches::label($decision->batch_id),
            action: $decision->action,
            modelAction: $decision->model_action,
            submittedBy: $decision->submitter->name,
            submittedReason: $decision->submitted_reason,
            submittedAt: $decision->submitted_at->toIso8601String(),
            chargeAt: is_string($chargeAt) ? $chargeAt : null,
        );
    }
}
