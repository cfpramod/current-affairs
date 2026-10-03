# QA and logistics approver wireframes

Status: ready for visual review; publication authorized · Updated: 2026-10-03

## State

Local design artifacts on feat/build-integration in the clean Current Affairs
checkout. Follow the user-supplied operator HTML's sketch style. The user clarified
logistics actions are expedite and reroute, not production resequencing.

## Done

- Three QA views: decision first, evidence workbench and review queue.
- Three logistics views: decision first, options workbench and review queue.
- Shared theme and local rationale-dialog previews; no live approvals or calls.
- Distinguish illustrative QA evidence from computed historical expedite metrics;
  current reroute availability remains explicitly unsupported.
- docs/design/approver-wireframes.md is the design and implementation handback.
- Browser verification: both pages render with the shared theme at 1440 px and
  390 px without horizontal overflow; dialogs submit a rationale and update the
  local preview log; no browser errors. Screenshots inspected visually.

## Next

- User chooses the role layouts before app implementation.
- User requested opening both files before pushing to origin; both were opened
  in separate browser tabs. Publish the design artifacts on feat/build-integration.
- App implementation and any PR merge still need separate user direction.
