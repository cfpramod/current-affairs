<?php

declare(strict_types=1);

namespace App\Actions\Batches;

use App\Models\BatchDecision;
use Illuminate\Database\Eloquent\Builder;

final class ResetBatchDecisions
{
    /**
     * Clear decisions so the demo flow can be rehearsed again: one batch's,
     * or every batch's when no batch is given. Returns the number removed.
     */
    public function handle(?string $batchId = null): int
    {
        return BatchDecision::query()
            ->when($batchId !== null, fn (Builder $query): Builder => $query->where('batch_id', $batchId))
            ->delete();
    }
}
