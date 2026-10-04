<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Exceptions\DataEngineUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Read-only client for the stored results served by the Python data engine.
 */
final class DataEngine
{
    /**
     * Every stored batch assessment, across datasets and cutoffs.
     *
     * @return list<array<string, mixed>>
     *
     * @throws DataEngineUnavailable
     */
    public function batchAssessments(): array
    {
        /** @var list<array<string, mixed>> */
        return $this->get('/api/integration/batch-assessments')->json();
    }

    /**
     * The latest stored assessment for a batch, or null when none is stored.
     *
     * @return ?array<string, mixed>
     *
     * @throws DataEngineUnavailable
     */
    public function latestBatchAssessment(string $datasetId, string $batchId): ?array
    {
        $response = $this->get(
            '/api/integration/batches/'.rawurlencode($batchId).'/assessments/latest',
            ['dataset_id' => $datasetId],
            allowNotFound: true,
        );

        return $response->notFound() ? null : $response->json();
    }

    /**
     * Stored synthetic operations datasets (batches, shipments, readings).
     *
     * @return list<array<string, mixed>>
     *
     * @throws DataEngineUnavailable
     */
    public function operations(): array
    {
        /** @var list<array<string, mixed>> */
        return $this->get('/api/integration/operations')->json();
    }

    /**
     * @param  array<string, string>  $query
     *
     * @throws DataEngineUnavailable
     */
    private function get(string $path, array $query = [], bool $allowNotFound = false): Response
    {
        try {
            $response = Http::acceptJson()
                ->timeout(5)
                ->get(mb_rtrim((string) config('integration.url'), '/').$path, $query);

            if ($allowNotFound && $response->notFound()) {
                return $response;
            }

            return $response->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new DataEngineUnavailable('The data engine is unavailable.', $exception->getCode(), previous: $exception);
        }
    }
}
