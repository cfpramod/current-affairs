<?php

declare(strict_types=1);

use App\Enums\BatchDecisionStatus;
use App\Enums\QaProspect;
use App\Models\BatchDecision;
use Database\Seeders\DemoUserSeeder;

/*
 * The live presentation, end to end in a real browser: the operator
 * quarantines the hot-day batch, QA accepts it with a next action. The engine
 * is faked from the team's stored demo outputs, and the "." autofill types
 * every reason, exactly as on stage.
 */

beforeEach(function (): void {
    $this->seed(DemoUserSeeder::class);
    config(['demo.autofill' => true, 'briefing.enabled' => false]);
    fakeEngine();
});

test('the hot-day demo: the operator quarantines and QA accepts with a next action', function () {
    $page = visit('/login')
        ->fill('email', 'operator@example.com')
        ->fill('password', 'secret')
        ->press('Log in')
        ->assertSee('Needs your decision')
        ->assertSee('Batch 002');

    $page->navigate('/batches/'.HOT_BATCH)
        ->assertSee('Batch 002 charges R2 at 18:00 Basel')
        ->assertSee('AT RISK')
        ->assertSee('148 of 120 min outside 2–8 °C')
        ->click('@choose-quarantine')
        ->keys('@decision-reason', '.')
        ->wait(2)
        ->assertValue('@decision-reason', 'Lot spent 148 of its 120 allowed minutes above 8 °C on the hot afternoon; hold it for QA.')
        ->click('@submit-decision')
        ->wait(1)
        ->assertNoJavaScriptErrors();

    $request = BatchDecision::query()->sole();
    expect($request->status)->toBe(BatchDecisionStatus::Pending);

    $page->click('Night Operator')
        ->click('Log out')
        ->fill('email', 'qa@example.com')
        ->fill('password', 'secret')
        ->press('Log in')
        ->assertSee('Waiting for your review')
        ->assertSee('Quarantine requested by Night Operator');

    $page->navigate('/batches/'.HOT_BATCH)
        ->click('@accept-material')
        ->keys('@review-reason', '.')
        ->wait(2)
        ->keys('@review-next-action', '.')
        ->wait(2)
        ->check('@review-confirm')
        ->click('@submit-review')
        ->wait(1)
        ->assertSee('Block lot 002 in the ERP and sample it for stability testing.')
        ->assertNoJavaScriptErrors();

    $request->refresh();
    expect($request->status)->toBe(BatchDecisionStatus::Accepted)
        ->and($request->review_reason)->toBe('Excursion confirmed in the readings; the lot cannot be used until assessed.')
        ->and($request->qa_prospect)->toBe(QaProspect::PotentiallyUsable)
        ->and($request->responsibility_confirmed)->toBeTrue();
});
