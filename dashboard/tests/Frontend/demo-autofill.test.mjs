import assert from 'node:assert/strict';
import test from 'node:test';
import { demoText } from '../../resources/js/lib/demo-autofill.ts';

const HOT_BATCH = 'operations-demo-v1-batch-002';

void test('the hot-day batch has its own prepared quarantine story', () => {
    assert.match(
        demoText(HOT_BATCH, 'submit', 'QUARANTINE'),
        /148 of its 120 allowed minutes/,
    );
    assert.match(demoText(HOT_BATCH, 'accept'), /Excursion confirmed/);
    assert.match(demoText(HOT_BATCH, 'next'), /Block lot 002/);
});

void test('other batches and actions fall back to a generic text', () => {
    assert.match(demoText(HOT_BATCH, 'submit', 'EXPEDITE'), /faster slot/);
    assert.match(
        demoText('operations-demo-v1-batch-004', 'deny'),
        /returning it to the operator/,
    );
    assert.ok(demoText('unknown-batch', 'submit').length > 0);
});

void test('the buffer text matches a batch covered by released stock', () => {
    const text = demoText(
        'operations-action-demo-v1-batch-001',
        'submit',
        'BUFFER',
    );

    assert.match(text, /released safety stock/i);
    assert.doesNotMatch(text, /late/);
});
