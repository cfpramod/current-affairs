<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Batches\ResetBatchDecisions;
use App\Actions\Batches\ReviewBatchDecision;
use App\Actions\Batches\SubmitBatchDecision;
use App\Enums\BatchDecisionStatus;
use App\Exceptions\DataEngineUnavailable;
use App\Exceptions\DecisionNotAllowed;
use App\Http\Requests\ReviewBatchDecisionRequest;
use App\Http\Requests\StoreBatchDecisionRequest;
use App\Models\BatchDecision;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class BatchDecisionController extends Controller
{
    /**
     * Submit the operator's chosen action and reason.
     */
    public function store(StoreBatchDecisionRequest $request, string $batch, #[CurrentUser] User $user, SubmitBatchDecision $submit): RedirectResponse
    {
        try {
            $decision = $submit->handle($batch, $user, $request->toData());
        } catch (DecisionNotAllowed|DataEngineUnavailable $exception) {
            return back()->withErrors(['decision' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $decision->status === BatchDecisionStatus::Accepted
            ? __(':action recorded.', ['action' => $decision->action->label()])
            : __(':action sent to :role for review.', ['action' => $decision->action->label(), 'role' => $decision->required_role->label()])]);

        return to_route('batches.show', $batch);
    }

    /**
     * Accept or deny an operator's request as the responsible role.
     */
    public function review(ReviewBatchDecisionRequest $request, BatchDecision $batchDecision, #[CurrentUser] User $user, ReviewBatchDecision $review): RedirectResponse
    {
        try {
            $decision = $review->handle($batchDecision, $user, $request->toData());
        } catch (DecisionNotAllowed $decisionNotAllowed) {
            return back()->withErrors(['review' => $decisionNotAllowed->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $decision->status === BatchDecisionStatus::Accepted
            ? __('Request accepted.')
            : __('Request denied and returned to the operator.')]);

        return to_route('batches.show', $decision->batch_id);
    }

    /**
     * Clear a batch's decisions so the demo can be rehearsed again.
     */
    public function destroy(string $batch, ResetBatchDecisions $reset): RedirectResponse
    {
        $reset->handle($batch);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Batch decisions reset.')]);

        return to_route('batches.show', $batch);
    }
}
