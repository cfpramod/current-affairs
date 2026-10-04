# Demo reliability fixes

Reliability review of feat/demo-codex-briefings (2026-10-04, cloud session).

Status: review done, fixes approved by @cfpramod, NOT yet implemented. Continue
on a laptop where `php artisan test` runs (the cloud container could not
install PHP packages). Branch facts: feat/build-integration is fully contained
in this branch (fast-forward, no conflicts). There is no `main` on GitHub; the
default branch is chore/team-setup.

Checks run: 320 Python tests pass (7 verification gates open by design);
17 frontend tests pass. Laravel suite not run in the cloud.

Approved fixes, in order (all on this branch):
1. BatchDecisionController::review only catches DecisionNotAllowed; engine
   down/slow gives a 500 on Accept/Deny. Catch DataEngineUnavailable too,
   like store().
2. BriefBatch::handle runs on every console render (BuildBatchConsole), so a
   failing API/Codex blocks page loads 30-90 s and only stored llm/codex
   results are cached. Page loads should only read the stored briefing or use
   the template; generation only in `batches:brief`. Add a test that a page
   view never calls a provider. Also start `artisan serve` with
   PHP_CLI_SERVER_WORKERS=4 in scripts/demo.sh.
3. scripts/demo.sh: after the engine wait loop, fail with a clear message if
   the API is not up or the child died; check port 8000 is free before
   serving; trap HUP too and kill child process trees so no orphaned engine
   stays on 8002; start the printf at line ~114 with a newline.
4. BatchController::show only catches DataEngineUnavailable; missing
   shipment/lot linkage throws DecisionNotAllowed from
   BatchDecisionPolicy::resolveShipmentsAndLots and gives a 500. Render
   gracefully instead.
5. Reroute is gated only by canChooseTransport in BatchDecisionPanel.vue; the
   server rejects it on the hot case after the reason is typed, and it skips
   the QA prospect gate Expedite has. Add rerouteAllowed /
   rerouteBlockedReason to the console state (policy, BatchConsoleData,
   console.ts, panel) mirroring expedite, with tests.
7. Small: demo-autofill.ts Buffer fallback says "incoming material is late",
   contradicting the buffer case; `--fresh` rewrites tracked files in
   output/; retrospective check uses lte in the policy but lt in
   BuildBatchConsole/ListBatches.
Known product gap, not in scope: after QA accepts quarantine the batch leaves
the operator's "Needs decision" list while transport is still open; use the
direct link in the demo.

Then: run Laravel tests, frontend types/tests/build, Python tests; push; ask
for an explicit yes, then fast-forward feat/build-integration and open a PR
that creates `main` from it.

Open team decision: hack-guard CI (--all) is red because 8 commits on this
branch (f63e4b8..e635514) and the old dashboard branch carry a teammate's
personal e-mail as author. Local commits and pushes of new work are not
blocked. Options: re-author those commits to the teammate's noreply address
when creating `main` (old branches keep the address until deleted), or ask
the teammate first. The other CI alarms come only from the dashboard branch
history.
