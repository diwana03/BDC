# Release 2.3.3-dev610

## Projector feed availability fix

- Removes table creation and repeated ALTER checks from the public projector token lookup.
- Prevents concurrent 2.5-second state polling from creating database metadata-lock pileups.
- Keeps token validation, token hashing, enabled-session checks and all projector state unchanged.
- Does not modify scoring data, judge links, competitor records or results.

## Evidence

- The supplied outer projector URL returns HTTP 200.
- Its state endpoint returns valid JSON and identifies the Competitors screen.
- Its inner feed endpoint hangs with zero response bytes.
- An invalid-token feed request also hangs before token validation, isolating the delay to the pre-validation schema ensure call.

## Validation status

- Projector hot-path regression check passed.
- Existing competitor, judge, stacked-card, flight-control and emergency-renderer checks passed.
- PHP runtime lint is unavailable in this workspace.
- Staging/runtime verification is required after deployment; Production remains blocked until then.

## Migration and deployment

- Database migration: none.
- Staging: not deployed.
- Production: not deployed.
