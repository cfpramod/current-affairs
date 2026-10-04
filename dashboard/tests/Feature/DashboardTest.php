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
    Http::preventStrayRequests();
});

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('the operator sees upcoming batches needing a decision and requests waiting on others', function () {
    fakeEngine();
    BatchDecision::factory()->create(['batch_id' => 'operations-demo-v1-batch-003']);
    BatchDecision::factory()->create(['batch_id' => 'operations-demo-v1-batch-004', 'status' => BatchDecisionStatus::Denied]);

    $this->actingAs(User::factory()->role(UserRole::Operator)->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('overview.role', 'operator')
            ->where('overview.needsDecision', fn ($items): bool => collect($items)->pluck('batchId')->all() === [HOT_BATCH, 'operations-demo-v1-batch-004'])
            ->where('overview.waitingOnOthers.0.batchId', 'operations-demo-v1-batch-003')
            ->has('overview.nextCharges', 3)
            ->has('overview.reviewQueue', 0)
            ->has('overview.watchlist', 0),
        );
});

test('QA sees its review queue, the temperature watchlist and what it reviewed', function () {
    fakeEngine();
    $qa = User::factory()->role(UserRole::Qa)->create();
    $waiting = BatchDecision::factory()->create(['batch_id' => 'operations-demo-v1-batch-003']);
    BatchDecision::factory()->create(['batch_id' => 'operations-demo-v1-batch-001', 'action' => ShipmentAction::Expedite, 'required_role' => UserRole::Logistics]);
    $reviewed = BatchDecision::factory()->create([
        'batch_id' => 'operations-demo-v1-batch-004',
        'status' => BatchDecisionStatus::Denied,
        'reviewed_by' => $qa->id,
        'review_reason' => 'Sensor fault.',
        'reviewed_at' => now(),
    ]);

    $this->actingAs($qa)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.role', 'qa')
            ->has('overview.reviewQueue', 1)
            ->where('overview.reviewQueue.0.id', $waiting->id)
            ->has('overview.watchlist', 1)
            ->where('overview.watchlist.0.batchId', HOT_BATCH)
            ->where('overview.recentlyReviewed.0.id', $reviewed->id)
            ->where('overview.recentlyReviewed.0.reviewReason', 'Sensor fault.')
            ->has('overview.needsDecision', 0),
        );
});

test('a batch with an open request leaves the QA watchlist', function () {
    fakeEngine();
    BatchDecision::factory()->create(['batch_id' => HOT_BATCH]);

    $this->actingAs(User::factory()->role(UserRole::Qa)->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('overview.watchlist', 0)->has('overview.reviewQueue', 1));
});

test('the review queue still loads when the engine is unavailable', function () {
    Http::fake(fn () => Http::failedConnection());
    BatchDecision::factory()->create();

    $this->actingAs(User::factory()->role(UserRole::Qa)->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.engineUnavailable', true)
            ->has('overview.reviewQueue', 1),
        );
});
