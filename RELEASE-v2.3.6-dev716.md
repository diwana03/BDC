# BDC v2.3.6-dev716

## Scope

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

- Focused WDC category-assignment visibility regression: passed.
- Existing WDC consolidated dashboard, premium workspace, registration integration and participant-first query regressions: passed.
- Focused council photo-link regression: passed.
- Existing adaptive portrait, WDC projection, photo persistence, universal safe-layout, final-result readability, matrix-spacing, projection identity/recovery/scale, roster, flight, finalists, BDC/SDC dashboard isolation, and Test/Live projection parity regressions: passed.
- Full JavaScript regression inventory: 211 of 248 passed. The remaining 37 failures were reproduced on the unchanged dev715 parent and are legacy version/fixture assertions outside this category-visibility change; the applicable legacy registration-category check was repaired and now passes.
- JavaScript syntax and final diff whitespace validation: passed.
- PHP syntax: not runtime-tested locally because PHP CLI is unavailable in this workspace.

## Parity Gate

- Testing Score Dashboard: shared Jack & Jill Test projection photo resolution checked statically for both BDC and SDC identities.
- Live Scoring Dashboard: official competitor photo write path and live projection read path checked statically; no scoring data or calculation path changed.
- Projector: Dance Cup contestant/results photo queries and Jack & Jill competitor, flight, matching, callback, finalist, result, and winner photo queries checked statically.
- Candidate/static validation: passed for the applicable dev716 category-visibility gate. The repository-wide legacy failures are recorded above and remain outside this change.
- Staging/runtime validation: not runtime-tested; deploy this exact `develop` candidate to Staging and verify Shalynn shows Bachata and her assigned category, then recheck adjusted photos on Salsa J&J, Bachata J&J, and the copied Dance Cup category.
- Production: untouched and blocked pending successful Staging runtime verification and separate approval.

## Migration

- No database migration required. Existing `wdc_identity_id` columns and canonical BDC/SDC identity tables are reused.

## Deployment

- Target: GitHub `develop` for Test/Staging only after local validation.
- Production remains untouched.
