<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Batches\ListPendingBatchReviews;
use App\Actions\Shipments\SummarizeShipments;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    /**
     * Show the overview of all shipments and the batch requests awaiting review.
     */
    public function __invoke(#[CurrentUser] User $user, SummarizeShipments $summarizeShipments, ListPendingBatchReviews $pendingBatchReviews): Response
    {
        return Inertia::render('Dashboard', [
            'overview' => $summarizeShipments->handle($user),
            'batchReviews' => $pendingBatchReviews->handle($user),
        ]);
    }
}
