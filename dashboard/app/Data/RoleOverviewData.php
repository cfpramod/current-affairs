<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\UserRole;
use Spatie\LaravelData\Data;

/**
 * What one role needs to see after logging in. Lists that don't apply to the
 * role stay empty.
 */
final class RoleOverviewData extends Data
{
    /**
     * @param  list<BatchListItemData>  $needsDecision
     * @param  list<BatchListItemData>  $waitingOnOthers
     * @param  list<BatchListItemData>  $nextCharges
     * @param  list<BatchReviewItemData>  $reviewQueue
     * @param  list<BatchListItemData>  $watchlist
     * @param  list<BatchReviewItemData>  $recentlyReviewed
     */
    public function __construct(
        public UserRole $role,
        public bool $engineUnavailable,
        public array $needsDecision,
        public array $waitingOnOthers,
        public array $nextCharges,
        public array $reviewQueue,
        public array $watchlist,
        public array $recentlyReviewed,
    ) {}
}
