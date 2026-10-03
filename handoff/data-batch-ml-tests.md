# Smartflow batch ML test data

Status: committed and pushed · Updated: 2026-10-03 · Branch: data/simulated-shipment-training

## Result

Generated 1,200 batches in 600 independent groups, 650 shipments and 9,637 readings. Twelve scenario families cover stock reservations, QA holds, partial/split supply, future plans, missing/stale evidence and same-lot double-count prevention. Each snapshot validates against OperationalDataset and cross-record conservation checks. Split: 840 train, 180 validation, 180 test batches; shared resources remain within their chronological group split.

## Files and use

The self-contained data/ml/batch-tests-v1 folder and neighboring zip include README, quickstart.py, manifest/checksums, snapshots, observable features, labels, case index and hidden simulated outcomes. Run quickstart.py immediately using Python. Optional --fit uses the declared NumPy/scikit-learn dependencies. All results concern the synthetic generator only.

## Validation

Package checksum/loading checks and a fitted synthetic baseline completed. Three new generator tests cover causal records, quantity conservation, explicit expected stock/QA/evidence cases, reproducible exports, zip integrity and disjoint chronological splits. Generation validates all 600 groups. The original 18-batch fixture is unchanged.

Published as commit 80f9bf0; staged and push privacy checks passed.

## Next

ML owner uses this package to implement batch-linked assessments. Generator/config/tests and the implementation briefs live on feat/build-integration. Keep IDs/scenario metadata/labels/hidden outcomes out of model inputs. Do not claim real-world accuracy. The five-minute presentation is later work; distinguish the journey scenes from the three-action logistics model and operational batches.
