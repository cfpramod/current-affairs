<?php

declare(strict_types=1);

use App\Actions\Batches\BriefBatch;
use App\Ai\BatchBriefingAgent;
use App\Enums\BriefingSource;
use App\Models\BatchBriefing;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function (): void {
    config(['briefing.codex.enabled' => false]);
    $this->withoutVite();
    Http::preventStrayRequests();
});

test('local Codex is used without an API key and its cached briefing is reused', function () {
    config(['briefing.enabled' => false, 'briefing.codex.enabled' => true]);
    BatchBriefingAgent::fake();
    Process::fake(function ($process) {
        $command = $process->command;
        file_put_contents($command[array_search('--output-last-message', $command, true) + 1], json_encode([
            'headline' => 'Stored temperature evidence needs QA review',
            'summary' => 'The sampled lot exceeded its demonstration budget. The model supports no action.',
            'qa_note' => 'QA must review; this is not release authorization.',
        ]));

        return Process::result();
    });

    $first = app(BriefBatch::class)->handle(hotAssessment());
    config(['briefing.codex.enabled' => false]);
    $second = app(BriefBatch::class)->handle(hotAssessment());

    expect($first->source)->toBe(BriefingSource::Codex)
        ->and($second->source)->toBe(BriefingSource::Codex)
        ->and($second->headline)->toBe($first->headline)
        ->and(BatchBriefing::query()->sole()->source)->toBe(BriefingSource::Codex);
    BatchBriefingAgent::assertNeverPrompted();
    Process::assertRan(fn ($process) => in_array('read-only', $process->command, true)
        && str_contains($process->input, '"action":"QUARANTINE"')
        && str_contains($process->input, '"complete_journey":false'));
    Process::assertRanTimes(fn ($process) => is_array($process->command), 1);
});

test('API failure tries local Codex before the template', function () {
    config(['briefing.enabled' => true, 'briefing.codex.enabled' => true]);
    BatchBriefingAgent::fake(fn () => throw new RuntimeException('Provider unavailable'));
    Process::fake(function ($process) {
        $command = $process->command;
        file_put_contents($command[array_search('--output-last-message', $command, true) + 1], '{"headline":"Stored facts","summary":"Human review is needed.","qa_note":null}');

        return Process::result();
    });

    expect(app(BriefBatch::class)->handle(hotAssessment())->source)->toBe(BriefingSource::Codex);
    Process::assertRanTimes(fn ($process) => is_array($process->command), 1);
});

test('unavailable or invalid Codex output falls back without caching', function (string $output, int $exitCode) {
    config(['briefing.enabled' => false, 'briefing.codex.enabled' => true]);
    Process::fake(function ($process) use ($output, $exitCode) {
        $command = $process->command;
        file_put_contents($command[array_search('--output-last-message', $command, true) + 1], $output);

        return Process::result(exitCode: $exitCode);
    });

    expect(app(BriefBatch::class)->handle(hotAssessment())->source)->toBe(BriefingSource::Template)
        ->and(BatchBriefing::query()->count())->toBe(0);
})->with([
    'CLI failed' => ['{}', 1],
    'invalid JSON' => ['not JSON', 0],
    'empty answer' => ['{"headline":"","summary":"","qa_note":null}', 0],
    'invalid QA field' => ['{"headline":"Facts","summary":"Facts","qa_note":7}', 0],
]);

test('a timed out local Codex process falls back to the template', function () {
    config(['briefing.enabled' => false, 'briefing.codex.enabled' => true]);
    Process::fake(fn () => throw new RuntimeException('Process timed out'));

    expect(app(BriefBatch::class)->handle(hotAssessment())->source)->toBe(BriefingSource::Template)
        ->and(BatchBriefing::query()->count())->toBe(0);
});

/** @return array<string, mixed> */
function hotAssessment(): array
{
    $assessments = json_decode((string) file_get_contents(base_path('../output/batch-assessments-demo.json')), true, 512, JSON_THROW_ON_ERROR);

    return collect($assessments)->firstWhere('batch_id', HOT_BATCH);
}

test('without an API key the briefing is a template built from the stored evidence', function () {
    config(['briefing.enabled' => false]);
    BatchBriefingAgent::fake();

    $briefing = app(BriefBatch::class)->handle(hotAssessment());

    expect($briefing->source)->toBe(BriefingSource::Template)
        ->and($briefing->headline)->toBe('Over temperature budget: 148 of 120 min outside 2–8 °C')
        ->and($briefing->summary)->toContain('The model recommends QUARANTINE')
        ->and($briefing->qaNote)->not->toBeNull();
    BatchBriefingAgent::assertNeverPrompted();
});

test('with an API key the LLM words the facts once and the briefing is reused', function () {
    config(['briefing.enabled' => true, 'briefing.model' => 'claude-opus-5-5', 'briefing.codex.enabled' => true]);
    Process::fake();
    BatchBriefingAgent::fake([[
        'headline' => 'Batch 002 should not charge: the lot overheated',
        'summary' => 'It spent 148 of 120 allowed minutes outside 2–8 °C.',
        'qa_note' => 'QA should review the excursion.',
    ]]);

    $first = app(BriefBatch::class)->handle(hotAssessment());
    $second = app(BriefBatch::class)->handle(hotAssessment());

    expect($first->source)->toBe(BriefingSource::Llm)
        ->and($first->model)->toBe('claude-opus-5-5')
        ->and($second->headline)->toBe('Batch 002 should not charge: the lot overheated')
        ->and(BatchBriefing::query()->sole()->assessment_id)->toBe(hotAssessmentId());
    BatchBriefingAgent::assertPromptedTimes(1);
    Process::assertNothingRan();
    BatchBriefingAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('"minutes_outside_range":148')
        && $prompt->contains('"action":"QUARANTINE"'));
});

test('a provider error falls back to the template without storing anything', function () {
    config(['briefing.enabled' => true]);
    BatchBriefingAgent::fake(fn () => throw new RuntimeException('Provider unavailable'));

    $briefing = app(BriefBatch::class)->handle(hotAssessment());

    expect($briefing->source)->toBe(BriefingSource::Template)
        ->and(BatchBriefing::query()->count())->toBe(0);
});

test('an empty LLM answer falls back to the template', function () {
    config(['briefing.enabled' => true]);
    BatchBriefingAgent::fake([['headline' => '', 'summary' => '', 'qa_note' => null]]);

    expect(app(BriefBatch::class)->handle(hotAssessment())->source)->toBe(BriefingSource::Template)
        ->and(BatchBriefing::query()->count())->toBe(0);
});

test('the batch console includes the briefing', function () {
    config(['briefing.enabled' => false]);
    fakeEngine();

    $this->actingAs(User::factory()->create())
        ->get(route('batches.show', HOT_BATCH))
        ->assertInertia(fn (Assert $page) => $page
            ->where('batch.briefing.source', 'template')
            ->where('batch.briefing.headline', 'Over temperature budget: 148 of 120 min outside 2–8 °C'),
        );
});

test('a console page view never calls a briefing provider', function () {
    config(['briefing.enabled' => true, 'briefing.codex.enabled' => true]);
    fakeEngine();
    BatchBriefingAgent::fake();
    Process::fake();

    $this->actingAs(User::factory()->create())
        ->get(route('batches.show', HOT_BATCH))
        ->assertInertia(fn (Assert $page) => $page->where('batch.briefing.source', 'template'));

    BatchBriefingAgent::assertNeverPrompted();
    Process::assertNothingRan();
    expect(BatchBriefing::query()->count())->toBe(0);
});

test('the batch console shows a prepared briefing', function () {
    fakeEngine();
    BatchBriefing::query()->create([
        'assessment_id' => hotAssessmentId(),
        'batch_id' => HOT_BATCH,
        'source' => 'codex',
        'model' => 'Codex CLI',
        'headline' => 'Prepared ahead of the demo',
        'summary' => 'From the stored facts.',
        'qa_note' => null,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('batches.show', HOT_BATCH))
        ->assertInertia(fn (Assert $page) => $page
            ->where('batch.briefing.source', 'codex')
            ->where('batch.briefing.headline', 'Prepared ahead of the demo'),
        );
});

test('briefings can be prepared for every batch ahead of a demo', function () {
    config(['briefing.enabled' => true]);
    fakeEngine();
    BatchBriefingAgent::fake(fn () => ['headline' => 'Briefed', 'summary' => 'From the stored facts.', 'qa_note' => null]);

    $this->artisan('batches:brief')
        ->expectsOutput(HOT_BATCH.': AI briefing')
        ->assertSuccessful();

    expect(BatchBriefing::query()->count())->toBe(4);
});
