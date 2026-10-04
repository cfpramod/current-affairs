<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Batches\BuildBatchConsole;
use App\Actions\Batches\ListBatches;
use App\Data\BatchConsoleData;
use App\Exceptions\DataEngineUnavailable;
use App\Exceptions\DecisionNotAllowed;
use Inertia\Inertia;
use Inertia\Response;

final class BatchController extends Controller
{
    /**
     * List production batches with a stored assessment.
     */
    public function index(ListBatches $listBatches): Response
    {
        try {
            return Inertia::render('batches/Index', ['batches' => $listBatches->handle(), 'unavailable' => false]);
        } catch (DataEngineUnavailable) {
            return Inertia::render('batches/Index', ['batches' => [], 'unavailable' => true]);
        }
    }

    /**
     * Show the operator console for one batch.
     */
    public function show(string $batch, BuildBatchConsole $buildConsole): Response
    {
        try {
            $console = $buildConsole->handle($batch);
        } catch (DataEngineUnavailable) {
            return Inertia::render('batches/Show', ['batch' => null, 'unavailable' => true, 'problem' => null]);
        } catch (DecisionNotAllowed $decisionNotAllowed) {
            return Inertia::render('batches/Show', ['batch' => null, 'unavailable' => false, 'problem' => $decisionNotAllowed->getMessage()]);
        }

        abort_if(! $console instanceof BatchConsoleData, 404);

        return Inertia::render('batches/Show', ['batch' => $console, 'unavailable' => false, 'problem' => null]);
    }
}
