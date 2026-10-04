<?php

declare(strict_types=1);

namespace App\Actions\Batches;

use App\Data\BatchDecisionInputData;
use App\Enums\BatchDecisionStatus;
use App\Enums\ShipmentAction;
use App\Enums\UserRole;
use App\Exceptions\DataEngineUnavailable;
use App\Exceptions\DecisionNotAllowed;
use App\Models\BatchDecision;
use App\Models\User;
use Carbon\CarbonImmutable;

final readonly class SubmitBatchDecision
{
    public function __construct(private FindLatestBatchAssessment $findLatest) {}

    /**
     * Record the operator's chosen action and reason against the exact stored
     * assessment. Operator-owned actions take effect immediately; the others
     * wait for review by the responsible role.
     *
     * @throws DecisionNotAllowed
     * @throws DataEngineUnavailable
     */
    public function handle(string $batchId, User $user, BatchDecisionInputData $input): BatchDecision
    {
        if ($user->role !== UserRole::Operator) {
            throw new DecisionNotAllowed('Only operators submit batch decisions.');
        }

        $assessment = $this->findLatest->handle($batchId);

        if ($assessment === null) {
            throw new DecisionNotAllowed('No stored assessment exists for this batch.');
        }

        if ($assessment['assessment_id'] !== $input->assessmentId) {
            throw new DecisionNotAllowed('A newer assessment is available. Reload before deciding.');
        }

        if (BatchDecision::query()->where('batch_id', $batchId)->active()->exists()) {
            throw new DecisionNotAllowed('This batch already has an open or accepted decision.');
        }

        $requiredRole = $input->action->approver();
        $modelAction = $assessment['recommendation']['action'] ?? null;

        return BatchDecision::query()->create([
            'dataset_id' => $assessment['dataset_id'],
            'batch_id' => $batchId,
            'assessment_id' => $assessment['assessment_id'],
            'assessment_as_of' => CarbonImmutable::parse($assessment['as_of'])->utc(),
            'assessment' => $assessment,
            'model_action' => is_string($modelAction) ? ShipmentAction::from($modelAction) : null,
            'action' => $input->action,
            'required_role' => $requiredRole,
            'status' => $requiredRole === UserRole::Operator ? BatchDecisionStatus::Accepted : BatchDecisionStatus::Pending,
            'submitted_by' => $user->id,
            'submitted_reason' => $input->reason,
            'submitted_at' => now(),
        ]);
    }
}
