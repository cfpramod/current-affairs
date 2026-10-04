<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BatchDecisionStatus;
use App\Enums\ShipmentAction;
use App\Enums\UserRole;
use Carbon\CarbonInterface;
use Database\Factories\BatchDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An operator's proposed action for a production batch and its review by the
 * responsible role, tied to the exact stored engine assessment it was based on.
 *
 * @property-read int $id
 * @property string $dataset_id
 * @property string $batch_id
 * @property string $assessment_id
 * @property CarbonInterface $assessment_as_of
 * @property array<string, mixed> $assessment
 * @property ?ShipmentAction $model_action
 * @property ShipmentAction $action
 * @property UserRole $required_role
 * @property BatchDecisionStatus $status
 * @property int $submitted_by
 * @property string $submitted_reason
 * @property CarbonInterface $submitted_at
 * @property ?int $reviewed_by
 * @property ?string $review_reason
 * @property ?string $next_action
 * @property bool $responsibility_confirmed
 * @property ?CarbonInterface $reviewed_at
 * @property-read User $submitter
 * @property-read ?User $reviewer
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class BatchDecision extends Model
{
    /** @use HasFactory<BatchDecisionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Decisions that block a new submission for the same batch.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereIn('status', [BatchDecisionStatus::Pending, BatchDecisionStatus::Accepted]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assessment_as_of' => 'datetime',
            'assessment' => 'array',
            'model_action' => ShipmentAction::class,
            'action' => ShipmentAction::class,
            'required_role' => UserRole::class,
            'status' => BatchDecisionStatus::class,
            'submitted_at' => 'datetime',
            'responsibility_confirmed' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }
}
