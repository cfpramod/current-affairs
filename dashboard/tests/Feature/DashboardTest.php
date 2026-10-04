<?php

declare(strict_types=1);

use App\Enums\BatchDecisionStatus;
use App\Enums\ShipmentAction;
use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\BatchDecision;
use App\Models\Shipment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('the overview counts open shipments and those needing the viewer\'s role', function () {
    Assessment::factory()->for(Shipment::factory())->create([
        'recommended_action' => ShipmentAction::Quarantine,
        'approver_role' => UserRole::Qa,
    ]);
    $routine = Assessment::factory()->for(Shipment::factory())->create();

    $this->actingAs(User::factory()->role(UserRole::Qa)->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('overview.open.open', 2)
            ->where('overview.open.needsYou', 1)
            ->has('overview.needsYou', 1)
            ->where('overview.nextCharges', fn ($items) => collect($items)->pluck('id')->contains($routine->shipment_id)),
        );
});

test('the overview lists pending batch requests for the viewer\'s role only', function () {
    $waiting = BatchDecision::factory()->create([
        'batch_id' => 'operations-demo-v1-batch-002',
        'assessment' => ['production_readiness' => ['planned_charge_at' => '2026-10-03T16:00:00Z']],
    ]);
    BatchDecision::factory()->create(['action' => ShipmentAction::Expedite, 'required_role' => UserRole::Logistics]);
    BatchDecision::factory()->create(['status' => BatchDecisionStatus::Accepted]);

    $this->actingAs(User::factory()->role(UserRole::Qa)->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('batchReviews', 1)
            ->where('batchReviews.0.id', $waiting->id)
            ->where('batchReviews.0.label', 'Batch 002')
            ->where('batchReviews.0.action', 'QUARANTINE')
            ->where('batchReviews.0.chargeAt', '2026-10-03T16:00:00Z'),
        );
});
