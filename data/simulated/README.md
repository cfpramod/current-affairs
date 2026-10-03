# Simulated shipment training dataset

Team-contributed feature dataset: 3,000 simulated shipments, 14,252 chronological observation windows, 53 original data columns plus one privacy-review metadata column. Exported from the existing saved simulation on 3 October 2026; shipments were not regenerated. No private implementation, credentials or trained model binaries accompany this contribution.

## Files

- [simulated_shipment_observations.csv](simulated_shipment_observations.csv): full dataset for training/evaluation.
- [simulated_shipments_3000.csv](simulated_shipments_3000.csv): one row per shipment, its latest stored observation window. Useful for inspection; it omits earlier training windows.
- [manifest.json](manifest.json): checksums, dimensions, column names and original split counts.

## Splits and labels

| Split | Shipments | Observation windows |
| --- | ---: | ---: |
| train | 1,800 | 8,516 |
| validation | 600 | 2,848 |
| test | 600 | 2,888 |

Keep all rows for a shipment in its supplied split. Evaluate anomaly injections using the same parent shipment split. Neither identifiers nor split labels are prediction features.

`shipment_id` groups windows. `sample_index` is zero-based and chronological within that shipment; `sample_count` is its total stored windows. `data_source` is always `SIMULATED`.

`privacy_review` is non-feature metadata. Some generated decimal values resemble phone/AHV identifiers to the mandatory privacy scanner. After human confirmation, those rows carry `hack-guard:allow user-confirmed simulated numeric false positive`. All original data cells are unchanged. Ignore this column for training and evaluation.

Targets, which must not enter model inputs:

- `on_time`: 1 when final simulated dock ETA is at or before the charge slot; otherwise 0, including suspension.
- `qa_budget_exceeded`: 1 when final cumulative simulated time outside 2–8 °C exceeds the assumed 120-minute allowance. This is a simulation outcome, not a QA disposition.
- `remaining_min`: minutes from the observation cutoff to final simulated dock ETA, floored at zero. Blank means suspended/no arrival; do not replace with zero. ETA models should evaluate conditional on route completion.

## Prediction features

Use only these 15 columns for the supplied arrival/thermal/ETA feature representation:

| Column | Meaning |
| --- | --- |
| time_to_slot_min | Minutes until the planned batch charge |
| planned_remaining_min | Known planned transport/handling time remaining |
| river_route_level_cm | Simulated river sensitivity, clamped at the configured suspension threshold |
| current_temp_c | Latest observed-prefix simulated product temperature |
| recent_mean_c, recent_max_c | Mean/maximum of the last 12 prefix readings |
| trend_c_per_min | Temperature change over that recent window divided by elapsed minutes |
| excursion_min | Cumulative simulated minutes outside the product band at the cutoff |
| budget_used | Excursion minutes divided by the assumed allowance |
| ambient_c | Latest simulated ambient temperature |
| remaining_exposure_min | Known planned unrefrigerated time remaining |
| segment_code | Zero-based route index |
| route_progress | Route index divided by route length; not elapsed-time progress |
| segment_elapsed_min | Minutes elapsed in the current observed segment |
| refrigerated | 0/1 refrigeration flag |

## Anomaly features

Six `anomaly_*` columns describe unmodified prefix readings: absolute latest temperature change (°C), absolute residual against one thermal-law step (°C), recent temperature standard deviation (°C), exposed flag (0/1), ambient/setpoint difference from product temperature (°C), and elapsed/planned segment duration ratio.

Each `injected_spike_*`, `injected_frozen_*`, `injected_gap_*` and `injected_delay_*` group contains the same six features after that named synthetic injection. These groups support detector evaluation; exclude them from arrival/ETA/thermal predictor inputs. Final shipment labels remain the unmodified journey outcomes, including for injected observation vectors. A gap injection can be a no-op on a short segment, so injection names alone do not guarantee distinguishable anomaly evidence.

## Provenance and limits

Generation seed: `20261003`. The three existing scene templates supplied route/material structure; synthetic sampling varied river sensitivity (30–180 cm), extra dock wait (0–300 minutes), handover duration factors (0.6–3.2), barge duration factors (0.8–1.2), charge-time shift (−12 to +12 hours), ambient offset (−2 to +2 °C), Basel ambient target (10–40 °C) and Rotterdam ambient target (8–30 °C). Generated hourly ambient profiles add a 3 °C sinusoidal daily cycle; these are not provider observations. The journey simulator uses the configured thermal law and assumed 360-minute refrigerated/exposed response times.

Windows sample transfer, barge, Basel unload, site truck and dock segments at approximately their midpoint when present. Inputs represent visible prefixes and known plans; final outcomes are separate targets. This is a feature dataset, not full raw sensor histories. It lacks original observation timestamps, per-shipment simulation seeds and complete scenario/timeline records, so exact source regeneration and raw-series anomaly tests are not possible from these CSVs alone.

Simulation scores do not establish real shipment accuracy or QA suitability. Inspect class balance and performance by stage/outcome before relying on aggregate scores. The team can reuse these artifacts without adopting the implementation that originally generated them. Attribution/provenance is recorded in [SOURCES.md](../../docs/SOURCES.md); no additional provider observation records are included in these CSVs.
