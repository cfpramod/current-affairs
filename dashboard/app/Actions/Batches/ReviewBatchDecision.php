<?php

declare(strict_types=1);

namespace App\Actions\Batches;

use App\Data\BatchReviewInputData;
use App\Enums\BatchDecisionStatus;
use App\Enums\QaProspect;
use App\Enums\ShipmentAction;
use App\Exceptions\DataEngineUnavailable;
use App\Exceptions\DecisionNotAllowed;
use App\Models\BatchDecision;
use App\Models\User;
use App\Services\BatchDecisionPolicy;

final readonly class ReviewBatchDecision
{
    public function __construct(
        private FindLatestBatchAssessment $findLatest,
        private BatchDecisionPolicy $policy,
    ) {}

    /**
     * The responsible role accepts or denies an operator's request. Accepting
     * also commits that role to a stated next action and revalidates transport gates.
     *
     * @throws DecisionNotAllowed
     * @throws DataEngineUnavailable
     */
    public function handle(BatchDecision $decision, User $user, BatchReviewInputData $input): BatchDecision
    {
        $latestAssessment = $this->findLatest->handle($decision->batch_id);

        if ($latestAssessment === null) {
            throw new DecisionNotAllowed('No stored assessment exists for this batch.');
        }

        $this->policy->validateReview($decision, $user, $input, $latestAssessment);

        $qaProspect = null;
        if ($decision->action === ShipmentAction::Quarantine && $input->accept) {
            $qaProspect = $input->qaProspect ?? QaProspect::Unknown;
        }

        $decision->update([
            'status' => $input->accept ? BatchDecisionStatus::Accepted : BatchDecisionStatus::Denied,
            'reviewed_by' => $user->id,
            'review_reason' => $input->reason,
            'next_action' => $input->accept ? $input->nextAction : null,
            'responsibility_confirmed' => $input->accept,
            'qa_prospect' => $qaProspect,
            'reviewed_at' => now(),
        ]);

        return $decision;
    }
}
