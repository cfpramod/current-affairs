# Demo briefings

Briefings are tied to the immutable assessment ID. `batches:brief` generates them
in this order: configured API provider, opt-in local Codex CLI, then the evidence
template. Batch pages never call a provider: they show the stored briefing, or the
evidence template when none was prepared, so a slow or failing provider cannot
block a page load.
Codex receives the same facts and instructions as the API, including incomplete
journey status. It only words the evidence; it never selects an action or releases
a lot. Its JSON is validated before storage, and its source stays labelled when
the cached briefing is read later.

`bash scripts/demo.sh` enables local Codex only during briefing preparation.
Install/sign in to the Codex CLI on the presentation laptop beforehand. The CLI
uses that local login and still needs network access during generation. It runs
in an empty temporary folder with a read-only sandbox, without loading user
configuration. Temporary output is removed after parsing. The prepared text is
stored in Laravel's local database and requires no model request during the demo.

## Demo without AI or Codex

```sh
bash scripts/demo.sh --case=hot --no-ai
```

`--no-ai` skips briefing preparation, so nothing needs a network, API key or
Codex login. Every batch page shows the evidence template, worded from the
stored assessment (for Batch 002: "Over temperature budget: 148 of 120 min
outside 2–8 °C", plus the QA note). The card is labelled as a template, not AI.
Briefings prepared on an earlier run stay stored and are still shown.

## Prepare AI briefings manually

To prepare manually while the selected engine API is running:

```sh
cd dashboard
SMARTFLOW_CODEX_BRIEFINGS=true php artisan batches:brief
```

`SMARTFLOW_CODEX_BINARY` can name an absolute CLI path if the server's PATH omits
it. `SMARTFLOW_CODEX_MODEL` optionally selects a model; unset uses the CLI default.
`SMARTFLOW_CODEX_TIMEOUT` defaults to 90 seconds. No API key is required for the
signed-in CLI path. If the executable, login, provider, response or timeout fails,
the template remains available. Templates are not cached as AI, so a later
preparation can retry. API and Codex successes are reused without regenerating.

Use pre-signed-in operator and QA browser profiles and open the chosen batch
directly before the slide handoff. Rehearse accept and deny, then return to the
operator to show the result and exact assessment history. In both Batch 002
cases the lot is over its temperature budget, so the model recommends Quarantine
for QA first; expedite or reroute opens only after QA records the lot as
potentially usable. The operator still requests the hold and QA accepts it.
The separate historical replay computes BUFFER for Batch 001.

## Select the deck's case

```sh
bash scripts/demo.sh --case=hot
bash scripts/demo.sh --case=buffer
bash scripts/demo.sh --case=expedite
```

The script selects the correct engine database and prints the direct batch URL.
Expedite is the default demo case. Hot is the original evidence/QA case.
Buffer and expedite are separate September 30
historical replays, labelled as such. An API already running with a different
dataset is detected; stop it before switching, or select a free port with
`SMARTFLOW_ENGINE_PORT`. The script writes the selected API URL into
`dashboard/.env`, so both the Laravel dev server and Herd use it. If port 8000
is taken, the script stops with a message; set `SMARTFLOW_APP_PORT` to serve the
app elsewhere.

For a roughly one-minute deck handoff: show the batch's briefing and evidence
(15 seconds), submit the operator action and reason (15 seconds), switch to the
pre-signed-in approver tab and accept with next action/responsibility (20 seconds),
then refresh the operator tab and show the exact evidence-linked result
(10 seconds). For Buffer, the operator owns the approval; use that case as a
shorter baseline. Prepare reasons in rehearsal and keep synthetic/demo labels.
Choose the case that follows the slide's claim; the hot evidence case does not
prove that ML recommended Quarantine.

Codex execution follows the [official non-interactive documentation](https://developers.openai.com/codex/noninteractive/).
