<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\ShipmentAction;
use App\Enums\UserRole;
use Spatie\LaravelData\Data;

final class BatchRecommendationData extends Data
{
    public function __construct(
        public ?ShipmentAction $action,
        public string $reason,
        public UserRole $approver,
        public bool $qaReviewRequired,
    ) {}

    /**
     * @param  array<string, mixed>  $recommendation
     */
    public static function fromEngine(array $recommendation): self
    {
        return new self(
            action: is_string($recommendation['action']) ? ShipmentAction::from($recommendation['action']) : null,
            reason: (string) $recommendation['reason'],
            approver: UserRole::from(mb_strtolower((string) $recommendation['requires_approval_by'])),
            qaReviewRequired: (bool) $recommendation['qa_review_required'],
        );
    }
}
