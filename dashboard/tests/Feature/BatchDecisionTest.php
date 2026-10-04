<?php

declare(strict_types=1);

use App\Enums\BatchDecisionStatus;
use App\Enums\ShipmentAction;
use App\Enums\UserRole;
use App\Models\BatchDecision;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    Http::preventStrayRequests();
});

function submitAs(UserRole $role, string $action, array $payload = []): Illuminate\Testing\TestResponse
{
    return test()->actingAs(User::factory()->role($role)->create())
        ->from(route('batches.show', HOT_BATCH))
        ->post(route('batches.decisions.store', HOT_BATCH), [
            'assessment_id' => hotAssessmentId(),
            'action' => $action,
            'reason' => 'Lot exceeded its excursion budget.',
            ...$payload,
        ]);
}

function reviewAs(UserRole $role, BatchDecision $decision, array $payload = []): Illuminate\Testing\TestResponse
{
    return test()->actingAs(User::factory()->role($role)->create())
        ->from(route('batches.show', HOT_BATCH))
        ->post(route('batch-decisions.review', $decision), [
            'accept' => true,
            'reason' => 'Excursion confirmed.',
            'next_action' => 'Block the lot and sample it.',
            'responsibility_confirmed' => true,
            ...$payload,
        ]);
}

test('the batch list summarises readiness and temperature from stored assessments', function () {
    fakeEngine();

    $this->actingAs(User::factory()->create())
        ->get(route('batches.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('batches/Index')
            ->has('batches', 4)
            ->where('batches.1.batchId', HOT_BATCH)
            ->where('batches.1.reactor', 'R2')
            ->where('batches.1.answers.arrival.verdict', 'at_risk')
            ->where('batches.1.answers.quality.verdict', 'no')
            ->where('batches.1.recommendation.action', null)
            ->where('batches.0.retrospective', true),
        );
});

test('the batch pages report an unreachable engine instead of failing', function () {
    Http::fake(fn () => Http::failedConnection());

    $this->actingAs(User::factory()->create())
        ->get(route('batches.index'))
        ->assertInertia(fn (Assert $page) => $page->where('unavailable', true)->has('batches', 0));
});

test('an unknown batch is not found', function () {
    fakeEngine();

    $this->actingAs(User::factory()->create())
        ->get(route('batches.show', 'operations-demo-v1-batch-999'))
        ->assertNotFound();
});

test('the batch console shows the hot day evidence and readings up to the cutoff', function () {
    fakeEngine();

    $this->actingAs(User::factory()->create())
        ->get(route('batches.show', HOT_BATCH))
        ->assertInertia(fn (Assert $page) => $page
            ->component('batches/Show')
            ->where('batch.assessmentId', hotAssessmentId())
            ->where('batch.temperatures.0.status', 'OBSERVED_BUDGET_EXCEEDED')
            ->where('batch.temperatureSeries.0.points', fn ($points): bool => count($points) > 0
                && collect($points)->every(fn (array $p): bool => $p['at'] <= '2026-10-03T12:00:00Z'))
            ->where('batch.activeDecision', null),
        );
});

test('an operator quarantine request waits for QA and keeps the exact assessment', function () {
    fakeEngine();

    submitAs(UserRole::Operator, 'QUARANTINE')->assertSessionHasNoErrors()->assertRedirect(route('batches.show', HOT_BATCH));

    $decision = BatchDecision::query()->sole();
    expect($decision->status)->toBe(BatchDecisionStatus::Pending)
        ->and($decision->required_role)->toBe(UserRole::Qa)
        ->and($decision->action)->toBe(ShipmentAction::Quarantine)
        ->and($decision->model_action)->toBeNull()
        ->and($decision->assessment_id)->toBe(hotAssessmentId())
        ->and($decision->assessment['product_temperature'][0]['status'])->toBe('OBSERVED_BUDGET_EXCEEDED');
});

test('an operator-owned action takes effect without review', function () {
    $assessments = json_decode((string) file_get_contents(base_path('../output/batch-assessments-demo.json')), true, 512, JSON_THROW_ON_ERROR);
    $assessments[1]['production_readiness']['status'] = 'RESERVED_STOCK_SUFFICIENT';
    $assessments[1]['production_readiness']['released_reserved_quantity_kg'] = 50.0;

    fakeEngine($assessments);

    submitAs(UserRole::Operator, 'BUFFER')->assertSessionHasNoErrors();

    expect(BatchDecision::query()->sole()->status)->toBe(BatchDecisionStatus::Accepted);
});

test('only operators submit batch decisions', function () {
    fakeEngine();

    submitAs(UserRole::Qa, 'QUARANTINE')->assertSessionHasErrors(['decision' => 'Only operators submit batch decisions.']);

    expect(BatchDecision::query()->count())->toBe(0);
});

test('a decision on an outdated assessment is rejected', function () {
    fakeEngine();

    submitAs(UserRole::Operator, 'QUARANTINE', ['assessment_id' => str_repeat('a', 64)])
        ->assertSessionHasErrors(['decision' => 'A newer assessment is available. Reload before deciding.']);
});

test('a batch takes a new request only after the previous one was denied', function () {
    fakeEngine();
    submitAs(UserRole::Operator, 'QUARANTINE')->assertSessionHasNoErrors();

    submitAs(UserRole::Operator, 'QUARANTINE')->assertSessionHasErrors(['decision' => 'This batch already has an open or accepted material decision.']);

    reviewAs(UserRole::Qa, BatchDecision::query()->sole(), ['accept' => false])->assertSessionHasNoErrors();
    submitAs(UserRole::Operator, 'QUARANTINE')->assertSessionHasNoErrors();

    expect(BatchDecision::query()->pluck('status')->all())->toEqualCanonicalizing([BatchDecisionStatus::Denied, BatchDecisionStatus::Pending]);
});

test('QA accepts a quarantine request with a reason and a committed next action', function () {
    fakeEngine();
    submitAs(UserRole::Operator, 'QUARANTINE');
    $decision = BatchDecision::query()->sole();

    reviewAs(UserRole::Qa, $decision)->assertSessionHasNoErrors()->assertRedirect(route('batches.show', HOT_BATCH));

    $decision->refresh();
    expect($decision->status)->toBe(BatchDecisionStatus::Accepted)
        ->and($decision->review_reason)->toBe('Excursion confirmed.')
        ->and($decision->next_action)->toBe('Block the lot and sample it.')
        ->and($decision->responsibility_confirmed)->toBeTrue()
        ->and($decision->reviewer->role)->toBe(UserRole::Qa);
});

test('accepting needs a next action and confirmed responsibility', function () {
    fakeEngine();
    submitAs(UserRole::Operator, 'QUARANTINE');
    $decision = BatchDecision::query()->sole();

    reviewAs(UserRole::Qa, $decision, ['responsibility_confirmed' => false])
        ->assertSessionHasErrors(['review' => 'To accept, state the next action and confirm your team will take it.']);

    expect($decision->refresh()->status)->toBe(BatchDecisionStatus::Pending);
});

test('only the responsible role reviews a request', function () {
    fakeEngine();
    submitAs(UserRole::Operator, 'QUARANTINE');

    reviewAs(UserRole::Logistics, BatchDecision::query()->sole())->assertSessionHasErrors(['review' => 'This request needs QA review.']);
});

test('a denial records the reason without a next action', function () {
    fakeEngine();
    submitAs(UserRole::Operator, 'QUARANTINE');
    $decision = BatchDecision::query()->sole();

    reviewAs(UserRole::Qa, $decision, ['accept' => false, 'reason' => 'Readings are a sensor fault.'])->assertSessionHasNoErrors();

    $decision->refresh();
    expect($decision->status)->toBe(BatchDecisionStatus::Denied)
        ->and($decision->review_reason)->toBe('Readings are a sensor fault.')
        ->and($decision->next_action)->toBeNull();
});

test('a review while the engine is unreachable reports the outage instead of failing', function () {
    $decision = BatchDecision::factory()->create(['batch_id' => HOT_BATCH]);
    Http::fake(fn () => Http::failedConnection());

    reviewAs(UserRole::Qa, $decision)->assertRedirect(route('batches.show', HOT_BATCH))->assertSessionHasErrors('review');

    expect($decision->refresh()->status)->toBe(BatchDecisionStatus::Pending);
});

test('demo decisions can be reset', function () {
    BatchDecision::factory()->count(2)->create(['batch_id' => HOT_BATCH]);

    $this->actingAs(User::factory()->create())
        ->delete(route('batches.decisions.destroy', HOT_BATCH))
        ->assertRedirect(route('batches.show', HOT_BATCH));

    expect(BatchDecision::query()->count())->toBe(0);
});

test('the demo reset command clears every batch decision', function () {
    BatchDecision::factory()->create(['batch_id' => HOT_BATCH]);
    BatchDecision::factory()->create(['batch_id' => 'operations-demo-v1-batch-003']);

    $this->artisan('demo:reset')->expectsOutput('Cleared 2 batch decision(s).')->assertSuccessful();

    expect(BatchDecision::query()->count())->toBe(0);
});

test('demo autofill is shared only when switched on', function (bool $enabled) {
    config(['demo.autofill' => $enabled]);
    fakeEngine();

    $this->actingAs(User::factory()->create())
        ->get(route('batches.show', HOT_BATCH))
        ->assertInertia(fn (Assert $page) => $page->where('demoAutofill', $enabled));
})->with([
    'off' => [false],
    'on' => [true],
]);
