# BDC 2.3.3-dev188

## Summary

- Enforces the configured YES and alternate quotas directly in the judge scoring browser.
- Updates the role counters immediately as the judge makes or changes a selection.
- Implements the behavior on Testing first and provides the same behavior on Live.

## Quota behavior

- YES uses the round's configured limit: Tier 1 = 5, Tier 2 = 10, Tier 3 = 15, or the admin's locked override.
- YES limits are enforced independently for Leaders and Followers.
- A1, A2 and A3 can each be assigned once per role.
- Once a quota is full, equivalent choices on other competitors are visually dimmed.
- Tapping an exhausted choice leaves the existing selection unchanged and displays a clear notification.
- Changing the original selection immediately releases its quota.
- NO and LATER do not consume quotas.

## Live feedback and safety

- Testing counters update immediately on the browser without Save Draft or page refresh.
- Live counters update optimistically on tap and reconcile with the server response.
- A failed Live save restores the prior selection and counter state.
- Existing server validation remains authoritative and prevents bypass through modified requests.

## Validation

- Confirmed JavaScript syntax for the Testing quota controller.
- Confirmed Testing reads the active round `yes_count` rather than assuming 10.
- Confirmed Live uses the same configured YES limit and server-returned role state.
- Confirmed quota release works when changing YES/A1/A2/A3 to another selection.
- Confirmed `VERSION.json` parses and `git diff --check` passes.
- PHP CLI and browser automation are unavailable in this workspace; interactive mobile verification remains part of Staging validation.

## Database migrations

- No new migration in dev188.

## Deployment

- Source release only. Deploy dev188 to Staging first through Release Manager.
- Production deployment is not performed by the coding agent.
