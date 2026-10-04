<?php

declare(strict_types=1);

namespace App\Actions\Batches;

use App\Models\BatchDecision;

final class ResetBatchDecisions
{
    /**
     * Clear a batch's decisions so the demo flow can be rehearsed again.
     */
    public function handle(string $batchId): void
    {
        BatchDecision::query()->where('batch_id', $batchId)->delete();
    }
}
