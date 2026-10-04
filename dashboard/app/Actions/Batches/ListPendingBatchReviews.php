<?php

declare(strict_types=1);

namespace App\Actions\Batches;

use App\Data\BatchReviewItemData;
use App\Enums\BatchDecisionStatus;
use App\Models\BatchDecision;
use App\Models\User;

final class ListPendingBatchReviews
{
    /**
     * Operator requests waiting for the user's role, oldest first.
     *
     * @return list<BatchReviewItemData>
     */
    public function handle(User $user): array
    {
        return array_values(BatchDecision::query()
            ->with('submitter')
            ->where('status', BatchDecisionStatus::Pending)
            ->where('required_role', $user->role)
            ->oldest('submitted_at')
            ->get()
            ->map(BatchReviewItemData::fromModel(...))
            ->all());
    }
}
