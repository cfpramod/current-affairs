<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

const HOT_BATCH = 'operations-demo-v1-batch-002';

/**
 * Serve the team's stored demo assessments and operations from a fake engine.
 */
function fakeEngine(): void
{
    $assessments = json_decode((string) file_get_contents(base_path('../output/batch-assessments-demo.json')), true, 512, JSON_THROW_ON_ERROR);
    $operations = json_decode((string) file_get_contents(base_path('../output/operations-demo.json')), true, 512, JSON_THROW_ON_ERROR);

    Http::fake(function (Request $request) use ($assessments, $operations) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        if ($path === '/api/integration/batch-assessments') {
            return Http::response($assessments);
        }

        if ($path === '/api/integration/operations') {
            return Http::response([$operations]);
        }

        if (preg_match('#^/api/integration/batches/([^/]+)/assessments/latest$#', (string) $path, $match) === 1) {
            $record = collect($assessments)->firstWhere('batch_id', $match[1]);

            return $record === null ? Http::response([], 404) : Http::response($record);
        }

        return Http::response([], 404);
    });
}

function hotAssessmentId(): string
{
    $assessments = json_decode((string) file_get_contents(base_path('../output/batch-assessments-demo.json')), true, 512, JSON_THROW_ON_ERROR);

    return collect($assessments)->firstWhere('batch_id', HOT_BATCH)['assessment_id'];
}
