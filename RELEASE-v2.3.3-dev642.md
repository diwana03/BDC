# BDC v2.3.3-dev642 — Admin login token regeneration

## Changes

- Adds **Regenerate Login Token** to the two-factor verification step rendered by the canonical `/admin` entry point.
- Reuses the existing secure email-code issuer, immediately invalidates the previous code, and gives each replacement a fresh ten-minute expiry.
- Applies a 30-second resend cooldown to prevent accidental duplicate emails and records `two_factor_code_regenerated` in the audit log.
- Leaves `/admin/login.php` as a compatibility redirect to `/admin`; no separate login surface is introduced.
- Does not change projector, judge, registration, integration, OAuth, scoring, competitor, result, or event tokens.

## Validation

- Candidate/static: focused `/admin` route, CSRF, conditional control, cooldown, audit, invalidation-message, and legacy redirect assertions passed; version JSON and repository whitespace checks passed.
- Staging/runtime: Not Runtime-Tested. Deploy this exact commit to Staging and complete password entry, regeneration after the cooldown, old-code rejection, and new-code login before Production promotion.

## Parity Gate

- Testing Score Dashboard: not affected; authenticated dashboard routing only.
- Live Scoring Dashboard: not affected; authenticated dashboard routing only.
- Live Scoreboard/projector: not affected; no display session or presentation files changed.

## Migration

- No database migration.
