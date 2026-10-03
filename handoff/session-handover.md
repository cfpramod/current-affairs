# Session handover: central data engine and Laravel trial

Status: ready to continue · Updated: 2026-10-03 · Branch: feat/sqlite-data-engine · Owner: @cfpramod

## Goal and current state

The team wants one central owner for external data collection and shared synthetic operations, then to trial the Laravel dashboard using example data. Provider calls should stay in the data engine; ML and Laravel consume stored or exported data. Keep `.hack/` backups separate from shared project work.

The central engine and example datasets are committed and pushed on `feat/sqlite-data-engine`. Draft PR #1 is open against `feat/basel-logistics-layer`: https://github.com/cfpramod/current-affairs/pull/1. It is not merged. The teammate's Laravel work is on `origin/dashboard`; do not switch/merge branches without reviewing their current state. Seven existing verification gates were deselected during the engine regression run; 241 other tests passed. The later dataset-only export was checked for schema relationships, record counts, checksums and CSV/JSON cell parity.

## Completed

- SQLite central store and collector for Basel traffic, Rhine and MeteoSwiss observations; configured hourly polling, retries, provenance, overlapping refresh, revision history and process lock.
- Reproducible simulated operations fixture: 12 shipments, 18 batches, five inventory lots, 18 supply plans, two reservations and 261 temperature readings.
- Offline import of 166 cached external observations and successful stored-data run through the existing ML demo; bounded live collection succeeded for all three feeds, returning observations matching the cache.
- Laravel-ready JSON and CSV examples for each operational table under `data/examples/operations/`, with `README.md`, manifest and standard-library exporter `scripts/export-operation-examples.py`.
- User reviewed `origin/dashboard`. It has shipment pages, assessments and auditable decisions, but no batch models/pages yet. Its shipment schema differs from the engine's provisional shipment table. Laravel also needs `planned` shipment support and nullable actual departure for future plans. PHP/Composer were unavailable here, so the dashboard was statically reviewed; its test suite was not run.

## Next

1. Give the Laravel teammate the dataset folder on `feat/sqlite-data-engine` (or copy that folder into `dashboard`) and ask them to build the batch seeder/screens using its documented IDs and relationships. No engine merge is needed to use fixture files.
2. Trial batches 001–004 from the package README: normal arrival with 50 kg released stock; heat-exposed shipment and 20 kg stock shortfall; delay without released reservation; future planned shipment with no actual milestones/readings.
3. Align Laravel's migrations to its existing shipment/assessment/decision workflow and the batch/material/supply/inventory records. Keep existing Laravel shipment identifiers as primary keys and store fixture IDs as external references if that suits its migration. Agree a single migration owner before sharing one SQLite file.
4. Review PR #1 and resolve its unrelated branch ancestry deliberately. No merge has been authorized. ML-result persistence/scheduling and human decision history integration remain subsequent work.

## Files to read first

- `docs/DATA_ENGINE.md` — collector ownership, SQLite schema, commands and current integration limits.
- `data/examples/operations/README.md` — data relationships, import order, field meanings and trial cases.
- `handoff/feat-sqlite-data-engine.md` — engine state and validation record.
- `handoff/data-batch-examples.md` — example dataset package and next use.
- `origin/dashboard` Laravel migrations/models/actions — inspect from the remote ref before editing; PHP tooling was absent during the prior review.

Current worktree has an unrelated, untracked `web/node_modules` item; leave it alone. `.runtime/` files and `.hack/` remain local. The outer workspace also has an unrelated handoff file; work only in `.hack/current-affairs` for this project.

## Resume prompt

> Continue from `handoff/session-handover.md` in the nested `current-affairs` repository. Check the current dashboard branch and PR state, then help the team trial the shared synthetic batch examples. Keep external-provider calls centralized in the SQLite data engine. Do not merge a PR without explicit approval.
