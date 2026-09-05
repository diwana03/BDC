# BDC v2.3.3-dev648 — Country-optional Judge Database lookup

## Changes

- Keeps active Judge Database profiles searchable and selectable even when `country` and `country_code` are blank or NULL.
- Normalizes optional country metadata in the shared judge search response so a missing country produces an empty flag instead of breaking or dropping the autocomplete result.
- Preserves canonical Judge Database ID, full/display name, Judge ID and existing name / Judge ID / Instagram matching. Country remains optional metadata, never a requirement for a valid judge result.
- Keeps Test and Live Automatic Scoring on the same shared Judge Database selection path.
- Retains the dev647 projector identity change already in the current release: larger one-line first names with real national flag images after the name instead of JP / TH style country codes.

## Validation

- PHP 8.1 syntax gate: passed for the complete repository.
- Full JavaScript regression suite: passed after replacing the stale dev638 exact-version assertion with a forward-compatible dev638-or-newer/build-3344-or-newer gate.
- Added `tests/judge-country-optional-search-v648.js` to enforce country-independent Judge Database matching, null-safe country metadata, canonical Judge Database ID selection, and Test / Live automatic setup parity.
- Existing Judge Database search v594, Automatic Judge search v596, selection v597, Final Judge search parity and projector regression checks remain passing.

## Parity Gate

- Testing Score Dashboard: PASS — uses the shared canonical Judge Database directory/search path and accepts profiles without country.
- Live Scoring Dashboard: PASS — same shared canonical Judge Database path and selection contract.
- Projector: PASS / unchanged for this fix — dev647 already contains the approved larger one-line first-name + real-flag-after-name presentation and its projector regressions pass.

## Migration

None. No scoring, result, competitor, judge-assignment, points or schema data is changed by this release.

## Deployment status

- Candidate source: `develop`
- Static / CI gate: PASS before release manifest publication.
- Staging runtime: NOT YET RUNTIME-TESTED. Deploy the exact dev648 `develop` commit through BDC Release Manager, then verify a countryless judge can be found and selected in both Test and Live scoring setup.
- Production: BLOCKED until that exact Staging commit passes runtime verification and is explicitly promoted through Release Manager.
