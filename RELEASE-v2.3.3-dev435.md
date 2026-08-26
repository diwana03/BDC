# BDC v2.3.3-dev435

Build: 3141  
Date: 2026-08-27  
Status: Development candidate

## Changes

- Keeps one public `BDC Dance Cup` competition type and loads its choices from the real organizer-configured Dance Cup events and Final categories.
- Reuses the existing general category definition: dance style, entry format, level, performance type, and category name. The current Salsa/Bachata Solo, Partner Routine, and Team Choreography Open categories therefore work without becoming one-off hard-coded options.
- Allows one competitor request to select multiple Dance Cup categories.
- Requests a partner, routine, or team name when Couple, Duo, or Team is selected and enforces at least four dancers for Team Choreography.
- Stores normalized request-to-category rows plus event/category snapshots, so approval evidence survives later category editing or deletion.
- Adds an administrator summary showing the requested event, category, reusable format, partner/team name, and team size.
- Preserves Jack & Jill registration and keeps Dance Cup registration completely separate from permanent BDC divisions and Special Categories.

## Parity Gate

- Public portal and Admin approval: static validation covers new and existing profiles, multiple categories, server-side category revalidation, partner/team rules, normalized evidence, and the unchanged Jack & Jill option.
- Testing Score Dashboard: no isolated scoring tables, calculations, roster, judge links, or projection behavior changed.
- Live Scoring Dashboard: public choices are read from Live organizer-configured Final categories; no scoring result or progression writes occur.
- Live Scoreboard / projector: no feed, display, polling, or control files changed.

## Validation

- Candidate/static: focused v435 portal regression, full available Node regression suite, migration immutability check, and `git diff --check`.
- PHP syntax/runtime: Not Runtime-Tested locally because PHP CLI is unavailable.
- Staging/runtime: Not Runtime-Tested. Production remains blocked until the migration, new/update portal submission, multiple-category request, admin review, Jack & Jill regression, and Dance Cup scoring/projector sanity pass on Staging.

## Migration

- Adds `bdc_profile_request_dance_cup_categories` only. Existing requests, competitors, events, categories, divisions, scores, and results are not modified.

## Deployment

- Candidate intended for GitHub `develop` only. Production is untouched.
