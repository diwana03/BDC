# BDC v2.3.6-dev715

## Scope

- Links adjusted contestant photos to every Jack & Jill audience screen in both councils: Salsa uses the active SDC identity and Bachata uses the active BDC identity.
- Makes Test projection resolve the current official adjusted photo instead of a stale disposable Test copy; Live continues to read the live shared-person photo directly.
- Persists the selected WDC identity on new Manual and Automatic Dance Cup roster rows.
- Preserves `wdc_identity_id` when a Dance Cup category is copied.
- Recovers the adjusted WDC photo for an already-copied roster row only when its active WDC display-name match is unique.
- Preserves the approved dev714 adaptive projector layout and portrait sizing.

## Validation

- Focused council photo-link regression: passed.
- Existing adaptive portrait, WDC projection, photo persistence, universal safe-layout, final-result readability, matrix-spacing, projection identity/recovery/scale, roster, flight, finalists, BDC/SDC dashboard isolation, and Test/Live projection parity regressions: passed.
- JavaScript syntax and final diff whitespace validation: passed.
- PHP syntax: not runtime-tested locally because PHP CLI is unavailable in this workspace.

## Parity Gate

- Testing Score Dashboard: shared Jack & Jill Test projection photo resolution checked statically for both BDC and SDC identities.
- Live Scoring Dashboard: official competitor photo write path and live projection read path checked statically; no scoring data or calculation path changed.
- Projector: Dance Cup contestant/results photo queries and Jack & Jill competitor, flight, matching, callback, finalist, result, and winner photo queries checked statically.
- Candidate/static validation: passed for the applicable dev715 gate. Two older projector tests retain a legacy `2.3.3-dev*` version-only assertion and reject the current `2.3.6-dev715` version after their functional assertions pass; this predates and is unrelated to the photo-link change.
- Staging/runtime validation: not runtime-tested; deploy this exact `develop` candidate to Staging and verify adjusted photos on Salsa J&J, Bachata J&J, and the copied Dance Cup category.
- Production: untouched and blocked pending successful Staging runtime verification and separate approval.

## Migration

- No database migration required. Existing `wdc_identity_id` columns and canonical BDC/SDC identity tables are reused.

## Deployment

- Target: GitHub `develop` for Test/Staging only after local validation.
- Production remains untouched.
