<?php

declare(strict_types=1);

namespace App\Actions\Batches;

use App\Data\BatchReviewInputData;
use App\Enums\BatchDecisionStatus;
use App\Exceptions\DecisionNotAllowed;
use App\Models\BatchDecision;
use App\Models\User;

final class ReviewBatchDecision
{
    /**
     * The responsible role accepts or denies an operator's request. Accepting
     * also commits that role to a stated next action.
     *
     * @throws DecisionNotAllowed
     */
    public function handle(BatchDecision $decision, User $user, BatchReviewInputData $input): BatchDecision
    {
        if ($decision->status !== BatchDecisionStatus::Pending) {
            throw new DecisionNotAllowed('This request has already been reviewed.');
        }

        if ($user->role !== $decision->required_role) {
            throw new DecisionNotAllowed(sprintf('This request needs %s review.', $decision->required_role->label()));
        }

        if ($input->accept && (in_array($input->nextAction, [null, ''], true) || ! $input->responsibilityConfirmed)) {
            throw new DecisionNotAllowed('To accept, state the next action and confirm your team will take it.');
        }

        $decision->update([
            'status' => $input->accept ? BatchDecisionStatus::Accepted : BatchDecisionStatus::Denied,
            'reviewed_by' => $user->id,
            'review_reason' => $input->reason,
            'next_action' => $input->accept ? $input->nextAction : null,
            'responsibility_confirmed' => $input->accept,
            'reviewed_at' => now(),
        ]);

        return $decision;
    }
}
