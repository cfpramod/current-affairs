# Handoff: simulated shipment training data

Status: done · Updated: 2026-10-03 · Branch: data/simulated-shipment-training · Owner: @cfpramod

## Goal

Share the existing 3,000 simulated shipments with the ML teammate on a new GitHub branch.

## State and done

Prepared the existing latest-window and full-observation CSVs in data/simulated for publication on this branch. Added field/target descriptions, grouped split counts, generation assumptions and source documentation. Verified row counts, finite numbers, parent splits, chronological sample indices and latest-window correspondence. User confirmed numeric privacy matches as false positives. Added a documented non-feature privacy_review column on 204 summary and 708 observation rows; all 53 original data columns match the source exports cell-by-cell. CSV newlines are LF for Git; manifest dimensions/checksums are updated. Staged privacy guard, strict doc-check and whitespace checks pass. No private implementation or model binary is included.

## Next

ML teammate can use data/simulated/simulated_shipment_observations.csv for training, retain the supplied grouped splits and exclude metadata/target/injected columns as documented. Integration remains separate. No merge requested.

## Resume prompt

> Read this handoff and data/simulated/README.md on data/simulated-shipment-training in current-affairs. Use the contributed dataset for model evaluation, preserving grouped splits and preventing target leakage.
