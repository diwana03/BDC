# BDC 2.3.3-dev275 · Build 2981

## Random Match scoring lock

- Locks Random Match as soon as any Final judge opens scoring, saves a placement, or submits.
- Enforces the lock in the shared service, Live dashboard, Test dashboard, Emcee presenter, and Projection Control link generation.
- Adds an emergency override for Scorer, Master Scorer, and Super Admin.
- Requires a reason, the confirmation word `REMATCH`, and a final browser confirmation.
- The override clears invalid Final placements/results, resets the Final to Draft, reopens judge sessions, revokes the old Emcee link, and writes an audit entry.

## Projector parity

- Removes the separate legacy Test and Live rendering implementations.
- Keeps both legacy URLs working as compatibility routes into the shared `live-display` engine.
- Test and Live now use identical controls, layouts, effects, holding-screen safety, result locks, paging, and reveal behavior.
- Only the data source differs: isolated `bdc_test_*` tables for Test and official `bdc_*` tables for Live.

## Parity Gate

- **Testing Score Dashboard:** Random Match lock, authorised override, placement clearing, judge-session reopening, and Test data isolation checked.
- **Live Scoring Dashboard:** Matching lock, role authorization, confirmation requirements, audit call, and recovery behavior mirrored.
- **Projector / Emcee:** Shared controller, shared public display, legacy compatibility routes, holding-screen return, and server-side matching protection checked.

## Validation

- Candidate/static: JSON validation, whitespace validation, route delegation checks, shared-service protection checks, and Test/Live source parity completed.
- PHP CLI syntax/runtime: unavailable in the local workspace; must run during Staging deployment.
- Staging/browser runtime: pending deployment of this exact `develop` commit.
- Production promotion: blocked until Staging runtime validation passes.

## Migration and deployment

- Database migration: none.
- Production deployment: not performed.
