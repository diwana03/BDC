# BDC v2.3.6-dev717

## Scope

- Adds a clear Reject & Return to Scoring decision beside Approve on the confidential Super Admin Dance Cup review page for both Test and Live.
- Requires a meaningful rejection reason and records an audit entry with the selected data mode and original submitter details.
- Preserves judge marks and private comments, cancels stale tie-break links, removes the stale calculated ranking, reopens submitted judge sessions, and returns the category to Draft for correction and recalculation.
- Updates the approval queue, Automatic workspace, and live AJAX status wording so Super Admin can see that both Approve and Reject are available.
- Repairs the WDC competitor workspace so an identity assigned to a Dance Cup category shows the category's saved Salsa/Bachata style even when no separate formal WDC registration row exists.
- Merges linked Live and isolated Test Dance Cup roster assignments into the Registrations / Categories column, with an explicit TEST label for isolated assignments.
- Recovers older roster rows that predate WDC identity linkage only when their active WDC display-name match is unique.
- Links adjusted contestant photos to every Jack & Jill audience screen in both councils: Salsa uses the active SDC identity and Bachata uses the active BDC identity.
- Makes Test projection resolve the current official adjusted photo instead of a stale disposable Test copy; Live continues to read the live shared-person photo directly.
- Persists the selected WDC identity on new Manual and Automatic Dance Cup roster rows.
- Preserves `wdc_identity_id` when a Dance Cup category is copied.
- Recovers the adjusted WDC photo for an already-copied roster row only when its active WDC display-name match is unique.
- Preserves the approved dev714 adaptive projector layout and portrait sizing.

## Validation

- Focused Super Admin Dance Cup rejection regression for Test and Live: passed.
- Focused WDC category-assignment visibility regression: passed.
- Existing WDC consolidated dashboard, premium workspace, registration integration and participant-first query regressions: passed.
- Focused council photo-link regression: passed.
- Existing adaptive portrait, WDC projection, photo persistence, universal safe-layout, final-result readability, matrix-spacing, projection identity/recovery/scale, roster, flight, finalists, BDC/SDC dashboard isolation, and Test/Live projection parity regressions: passed.
- Full JavaScript regression inventory: 216 of 249 passed. The remaining 33 legacy version/fixture failures are outside this change; dev716's baseline was 211 of 248 passed with 37 such failures.
- JavaScript syntax and final diff whitespace validation: passed.
- PHP syntax: not runtime-tested locally because PHP CLI is unavailable in this workspace.

## Parity Gate

- Testing Score Dashboard: shared Jack & Jill Test projection photo resolution checked statically for both BDC and SDC identities.
- Live Scoring Dashboard: official competitor photo write path and live projection read path checked statically; no scoring data or calculation path changed.
- Projector: Dance Cup contestant/results photo queries and Jack & Jill competitor, flight, matching, callback, finalist, result, and winner photo queries checked statically.
- Candidate/static validation: passed the focused rejection suite and completed the full 249-test JavaScript inventory.
- Staging/runtime validation: not runtime-tested; deploy the exact dev717 `develop` candidate to Staging, reject one isolated Test result, confirm it returns to Draft with marks/comments preserved and recalculation required, then verify the corresponding Live review page exposes the same protected action without submitting it.
- Production: untouched and blocked pending successful Staging runtime verification and separate approval.

## Migration

- No database migration required. Existing `wdc_identity_id` columns and canonical BDC/SDC identity tables are reused.

## Deployment

- Target: GitHub `develop` for Test/Staging only after local validation.
- Production remains untouched.
