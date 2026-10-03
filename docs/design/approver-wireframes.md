# Approver console design exploration

Open [QA approver wireframes](qa-approver-wireframes.html) and
[logistics approver wireframes](logistics-approver-wireframes.html) directly in a
browser. Each offers three layouts: decision first, evidence workbench and review
queue. The pages adapt to narrow screens. Buttons open a rationale dialog and
preview a decision locally; there are no network calls or persisted approvals.

The supplied Operator Console Wireframes reference establishes the light paper
canvas, handwritten type, outlined evidence cards, blue decision panel and numbered
layout alternatives. These pages extend that direction without changing the app.
Styles live in [approver-theme.css](approver-theme.css); interactions live in
[approver-interactions.js](approver-interactions.js). Kalam is used when installed,
with local handwritten font fallbacks. No support.js or external font download is
required. This is a review artifact, not a replacement for the app's brand guide.

QA is lot-centric: quarantine request, product-temperature sample history,
interpolation gaps, observed/reported excursion, material limits, calibration,
stability and custody evidence, affected batches and prior disposition. Confirm
quarantine, request evidence or escalate; release requires its own human QA process.
The temperature drawing and queue are illustrative synthetic examples. Ambient
proxies and arrival probabilities cannot establish product quality.

Logistics is plan-centric: original journey versus expedite/reroute, timing benefit,
affected demand/deadlines, route restrictions, carrier capacity, transfer point,
handling/refrigeration, receiving windows, cost and booking confirmation. The
September 30 replay metrics come from the stored action demo documented in
[BATCH_ASSESSMENTS.md](../BATCH_ASSESSMENTS.md). Reroute is unavailable in that
profile; the design shows what evidence a separate reroute request would need.
No alternate route or probability is invented.

Implementation should use the existing roles and exact assessment identity,
record reason/reviewer/time, enforce required confirmations on the server and retain
an audit history. The queue/workflow and operational confirmation fields are design
proposals; they are not claims about the current batch read API. Missing data stays
unknown. QA review, transport authorization and dispatch are separate events.

Next product decision: choose a layout (or combine decision-first with the evidence
workbench) for each role before implementing these pages in Laravel.
