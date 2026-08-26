# BDC v2.3.3-dev433

Build: 3139  
Date: 2026-08-26  
Status: Development candidate

## Changes

- Expands the Automatic Live Judge Score Matrix from judge totals to every saved criterion mark, grouped by judge, followed by judge total and calculated place.
- Refreshes criterion marks, totals, completion status, and calculated place through the existing two-second live state request without page navigation.
- Makes the official five-criterion, 100-point score sheet the default for Solo, Couple, Duo, and Team categories: Timing; Musicality & Choreography; Difficulty; Dance Style Technique / Authenticity; Overall Presentation (Costume & Showmanship), each out of 20.
- Keeps criteria fully editable before category creation, so an administrator may rename, remove, add, or reweight criteria.

## Parity Gate

- Testing Score Dashboard: shared Automatic workspace, isolated `bdc_test_dance_cup_*` criteria and marks state, and shared live refresh integration statically verified.
- Live Scoring Dashboard: shared Automatic workspace, real `bdc_dance_cup_*` criteria and marks state, and shared live refresh integration statically verified.
- Live Scoreboard / projector: no projector payload, controls, or presentation behavior changed; regression tests remain required before Staging approval.

## Validation

- Candidate/static: focused v433 criterion-matrix regression, existing Dance Cup defaults/workflow suites, full available Node regression suite, and `git diff --check`.
- PHP syntax/runtime: Not Runtime-Tested locally because PHP CLI is unavailable in this workspace.
- Staging/runtime: Not Runtime-Tested. Production promotion remains blocked until this exact develop commit passes the complete Test, Live, judge, mobile, refresh, calculation, submit/lock, print, and projector sanity workflow on Staging.

## Migration

- None. Existing category criteria and scores remain unchanged; the new defaults apply only when creating a new category.

## Deployment

- Candidate intended for the GitHub `develop` release line only. No Production deployment is performed by this change.
