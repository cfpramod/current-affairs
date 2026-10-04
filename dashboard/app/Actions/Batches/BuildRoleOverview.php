<?php

declare(strict_types=1);

namespace App\Actions\Batches;

use App\Data\BatchListItemData;
use App\Data\BatchReviewItemData;
use App\Data\RoleOverviewData;
use App\Enums\BatchDecisionStatus;
use App\Enums\UserRole;
use App\Exceptions\DataEngineUnavailable;
use App\Models\BatchDecision;
use App\Models\User;

final readonly class BuildRoleOverview
{
    private const int NEXT_CHARGES = 5;

    private const int RECENT_REVIEWS = 5;

    public function __construct(
        private ListBatches $listBatches,
        private ListPendingBatchReviews $pendingReviews,
    ) {}

    /**
     * The operator sees what needs a decision and what waits on others; QA and
     * logistics see their review queue, what to watch and what they reviewed.
     */
    public function handle(User $user): RoleOverviewData
    {
        try {
            $batches = $this->listBatches->handle();
            $engineUnavailable = false;
        } catch (DataEngineUnavailable) {
            $batches = [];
            $engineUnavailable = true;
        }

        $upcoming = array_values(array_filter($batches, fn (BatchListItemData $b): bool => ! $b->retrospective));
        $isOperator = $user->role === UserRole::Operator;

        return new RoleOverviewData(
            role: $user->role,
            engineUnavailable: $engineUnavailable,
            needsDecision: $isOperator ? array_values(array_filter($upcoming, fn (BatchListItemData $b): bool => ! $b->decision instanceof \App\Data\BatchDecisionData || $b->decision->status === BatchDecisionStatus::Denied)) : [],
            waitingOnOthers: $isOperator ? array_values(array_filter($upcoming, fn (BatchListItemData $b): bool => $b->decision?->status === BatchDecisionStatus::Pending)) : [],
            nextCharges: $isOperator ? array_slice($upcoming, 0, self::NEXT_CHARGES) : [],
            reviewQueue: $isOperator ? [] : $this->pendingReviews->handle($user),
            watchlist: $isOperator ? [] : $this->watchlist($user->role, $upcoming),
            recentlyReviewed: $isOperator ? [] : $this->recentlyReviewed($user),
        );
    }

    /**
     * Batches the role should keep an eye on that have no open request yet:
     * temperature evidence for QA, incoming timing for logistics.
     *
     * @param  list<BatchListItemData>  $upcoming
     * @return list<BatchListItemData>
     */
    private function watchlist(UserRole $role, array $upcoming): array
    {
        return array_values(array_filter($upcoming, function (BatchListItemData $batch) use ($role): bool {
            if ($batch->decision instanceof \App\Data\BatchDecisionData && $batch->decision->status !== BatchDecisionStatus::Denied) {
                return false;
            }

            $answer = $role === UserRole::Qa ? $batch->answers->quality : $batch->answers->arrival;

            return in_array($answer->verdict, ['no', 'at_risk'], true);
        }));
    }

    /**
     * @return list<BatchReviewItemData>
     */
    private function recentlyReviewed(User $user): array
    {
        return array_values(BatchDecision::query()
            ->with('submitter')
            ->where('reviewed_by', $user->id)
            ->latest('reviewed_at')
            ->limit(self::RECENT_REVIEWS)
            ->get()
            ->map(BatchReviewItemData::fromModel(...))
            ->all());
    }
}
