# QA and logistics approver wireframes

Status: workflow revisions ready; publication authorized · Updated: 2026-10-04

## State

Local design artifacts on feat/build-integration in the clean Current Affairs
checkout. Follow the user-supplied operator HTML's sketch style. The user clarified
logistics actions are expedite and reroute, not production resequencing.
The first draft was committed locally as f580a3e before the user interrupted the
push. Read-only GitHub verification confirms origin remains at 5c3c643. The user
then clarified the approval flow and explicitly instructed not to commit yet.
The user subsequently authorized publishing the corrected wireframes together
with the new batch dashboard on origin/feat/build-integration.

## Done

- Three QA views: decision first, evidence workbench and review queue.
- Three logistics views: decision first, options workbench and review queue.
- Shared theme and local rationale-dialog previews; no live approvals or calls.
- Operator submits selected action and reason; approver accepts or denies with
  a separate reason. Acceptance requires a stated next action and confirmation.
- Logistics shows model EXPEDITE versus operator REROUTE, including the operator's
  override reason. Denial returns to the operator without approving a fallback.
- Distinguish illustrative QA evidence from computed historical expedite metrics;
  current reroute availability remains explicitly unsupported.
- docs/design/approver-wireframes.md is the design and implementation handback.
- Browser verification: both pages render with the shared theme at 1440 px and
  390 px without horizontal overflow; dialogs submit a rationale and update the
  local preview log; no browser errors. Screenshots inspected visually.

## Next

- User chooses the role layouts before app implementation.
- Both files are open in separate browser tabs for review.
- Publish the corrected wireframes and imported dashboard; the user has authorized
  this. The earlier interrupted push did not publish the first wireframe commit.
- App implementation and any PR merge still need separate user direction.
