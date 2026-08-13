# BDC 2.3.3-dev187

## Summary

- Adds a Columns selector to Saved Rounds on Testing and Live dashboards.
- Keeps every existing field and action available without restructuring scoring data.
- Replaces browser-dependent manual date-time entry with a date picker and clear 15-minute time dropdown.

## Saved Rounds presentation

- Optional columns can be shown or hidden independently; Event remains visible as the row identifier.
- The cleaner default hides the redundant Event Date and technical Updated timestamp while keeping Round Schedule visible.
- Show All restores all columns and Reset returns to the clean default.
- The browser remembers the selection and applies it consistently across Testing and Live.

## Round schedule controls

- Initial Heats/Final scheduling uses a date picker and time selector.
- Heats-to-Semifinal, Heats-to-Final, and Semifinal-to-Final scheduling use the same selector.
- Edit Draft Details uses the same selector.
- Server-side date-time validation and stored values remain unchanged.

## Validation

- Confirmed Testing and Live expose the same columns, defaults, and controls.
- Confirmed all scheduled fields still submit the existing `YYYY-MM-DDTHH:MM` value expected by the server.
- Confirmed `VERSION.json` parses and `git diff --check` passes.
- PHP CLI and browser automation are unavailable in this workspace; interactive verification remains part of Staging validation.

## Database migrations

- No new migration in dev187.

## Deployment

- Source release only. Deploy dev187 to Staging first through Release Manager.
- Production deployment is not performed by the coding agent.
