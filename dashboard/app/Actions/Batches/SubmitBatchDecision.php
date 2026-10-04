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
use App\Services\BatchDecisionPolicy;
use Carbon\CarbonImmutable;

final readonly class SubmitBatchDecision
{
    public function __construct(
        private FindLatestBatchAssessment $findLatest,
        private BatchDecisionPolicy $policy,
    ) {}

    /**
     * Record the operator's chosen action and reason against the exact stored
     * assessment, enforcing material hold and transport workflow rules.
     *
     * @throws DecisionNotAllowed
     * @throws DataEngineUnavailable
     */
    public function handle(string $batchId, User $user, BatchDecisionInputData $input): BatchDecision
    {
        $assessment = $this->findLatest->handle($batchId);

        if ($assessment === null) {
            throw new DecisionNotAllowed('No stored assessment exists for this batch.');
        }

        if ($assessment['assessment_id'] !== $input->assessmentId) {
            throw new DecisionNotAllowed('A newer assessment is available. Reload before deciding.');
        }

        $this->policy->validateSubmission($batchId, $user, $input->action, $assessment);

        $linkage = $this->policy->resolveShipmentsAndLots($assessment);
        $lane = $input->action->lane();
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
            'lane' => $lane,
            'shipment_id' => $linkage['shipment_ids'][0] ?? null,
            'lot_id' => $linkage['lot_ids'][0] ?? null,
            'required_role' => $requiredRole,
            'status' => $requiredRole === UserRole::Operator ? BatchDecisionStatus::Accepted : BatchDecisionStatus::Pending,
            'submitted_by' => $user->id,
            'submitted_reason' => $input->reason,
            'submitted_at' => now(),
        ]);
    }
}
