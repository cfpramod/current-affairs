<?php

declare(strict_types=1);

use App\Actions\Batches\BriefBatch;
use App\Ai\BatchBriefingAgent;
use App\Models\BatchBriefing;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function (): void {
    $this->withoutVite();
    Http::preventStrayRequests();
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

    expect($briefing->source)->toBe('template')
        ->and($briefing->headline)->toBe('Over temperature budget: 148 of 120 min outside 2–8 °C')
        ->and($briefing->summary)->toContain('The model supports no action from this evidence')
        ->and($briefing->qaNote)->not->toBeNull();
    BatchBriefingAgent::assertNeverPrompted();
});

test('with an API key the LLM words the facts once and the briefing is reused', function () {
    config(['briefing.enabled' => true, 'briefing.model' => 'claude-opus-5-5']);
    BatchBriefingAgent::fake([[
        'headline' => 'Batch 002 should not charge: the lot overheated',
        'summary' => 'It spent 148 of 120 allowed minutes outside 2–8 °C.',
        'qa_note' => 'QA should review the excursion.',
    ]]);

    $first = app(BriefBatch::class)->handle(hotAssessment());
    $second = app(BriefBatch::class)->handle(hotAssessment());

    expect($first->source)->toBe('llm')
        ->and($first->model)->toBe('claude-opus-5-5')
        ->and($second->headline)->toBe('Batch 002 should not charge: the lot overheated')
        ->and(BatchBriefing::query()->sole()->assessment_id)->toBe(hotAssessmentId());
    BatchBriefingAgent::assertPromptedTimes(1);
    BatchBriefingAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('"minutes_outside_range":148')
        && $prompt->contains('"action":null'));
});

test('a provider error falls back to the template without storing anything', function () {
    config(['briefing.enabled' => true]);
    BatchBriefingAgent::fake(fn () => throw new RuntimeException('Provider unavailable'));

    $briefing = app(BriefBatch::class)->handle(hotAssessment());

    expect($briefing->source)->toBe('template')
        ->and(BatchBriefing::query()->count())->toBe(0);
});

test('an empty LLM answer falls back to the template', function () {
    config(['briefing.enabled' => true]);
    BatchBriefingAgent::fake([['headline' => '', 'summary' => '', 'qa_note' => null]]);

    expect(app(BriefBatch::class)->handle(hotAssessment())->source)->toBe('template')
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

test('briefings can be prepared for every batch ahead of a demo', function () {
    config(['briefing.enabled' => true]);
    fakeEngine();
    BatchBriefingAgent::fake(fn () => ['headline' => 'Briefed', 'summary' => 'From the stored facts.', 'qa_note' => null]);

    $this->artisan('batches:brief')
        ->expectsOutput(HOT_BATCH.': AI briefing')
        ->assertSuccessful();

    expect(BatchBriefing::query()->count())->toBe(4);
});
