# BDC 2.3.3-dev367

## Final Emcee Matching Link

- Adds the missing dedicated **Emcee Matching Link** directly to the Final dashboard.
- Generates or regenerates restricted 12-hour Emcee access without sending the Emcee into the organiser's Projection Control.
- Shows the active secure URL with reliable Copy Link fallback, Open Emcee Matching and exact expiry time.
- Reuses the established event Live Display, secure random pairing service, countdown, reveal and audit workflow; no second matching or projector engine is introduced.
- Automatically prepares the event's existing projector session when the first Emcee link is generated.
- Prevents generation or regeneration after Final scoring has started and retains the existing emergency REMATCH recovery.
- Keeps the organiser's Event Projection control available as a separate management action.

## Parity Gate

### Candidate/static validation

- Testing implemented first in `admin/scoring-tests/index.php` with isolated `bdc_test_*` rounds, sessions, pairs and marks.
- Live mirrored in `admin/scoring/core.php` with real scoring data.
- Shared token, expiry, scoring lock and random matching remain in `app/Services/RandomPairingService.php`.
- Shared projector/session behavior remains in `admin/live-screen/control.php`, `pairing-presenter/index.php` and the existing Live Display engine.
- Shared reliable copy fallback is loaded from `public/js/bdc-copy-link-v345.js` on both dashboards.
- Added executable static parity regression `tests/emcee-matching-dashboard-v367.js`.

### Staging/runtime validation

- In an isolated Test Final, generate the Test Emcee link and verify Copy Link, Open Emcee Matching, 12-hour expiry, randomization, countdown and reveal on the existing Test projector.
- Repeat on a Live Final in Staging and verify the Emcee URL cannot access the organiser dashboard.
- Start Final scoring and confirm Generate or Regenerate is disabled and the protected service rejects direct attempts.
- Confirm pairing confirmation consumes the active Emcee link and emergency REMATCH revokes it.
- Production promotion remains blocked until these Staging checks pass.

## Migration

- No new database or configuration migration. The existing additive Emcee pairing-link and projector-session tables are reused.

## Deployment

- Candidate is intended for the GitHub `develop` release line only.
- Deploy the exact committed SHA to Staging through Release Manager.
- Production is untouched and must not be promoted before runtime validation passes.
