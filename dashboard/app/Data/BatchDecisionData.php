<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\BatchDecisionStatus;
use App\Enums\ShipmentAction;
use App\Enums\UserRole;
use App\Models\BatchDecision;
use Spatie\LaravelData\Data;

final class BatchDecisionData extends Data
{
    public function __construct(
        public int $id,
        public string $assessmentId,
        public ?ShipmentAction $modelAction,
        public ShipmentAction $action,
        public UserRole $requiredRole,
        public BatchDecisionStatus $status,
        public string $submittedBy,
        public string $submittedReason,
        public string $submittedAt,
        public ?string $reviewedBy,
        public ?string $reviewReason,
        public ?string $nextAction,
        public bool $responsibilityConfirmed,
        public ?string $reviewedAt,
    ) {}

    public static function fromModel(BatchDecision $decision): self
    {
        return new self(
            id: $decision->id,
            assessmentId: $decision->assessment_id,
            modelAction: $decision->model_action,
            action: $decision->action,
            requiredRole: $decision->required_role,
            status: $decision->status,
            submittedBy: $decision->submitter->name,
            submittedReason: $decision->submitted_reason,
            submittedAt: $decision->submitted_at->toIso8601String(),
            reviewedBy: $decision->reviewer?->name,
            reviewReason: $decision->review_reason,
            nextAction: $decision->next_action,
            responsibilityConfirmed: $decision->responsibility_confirmed,
            reviewedAt: $decision->reviewed_at?->toIso8601String(),
        );
    }
}
