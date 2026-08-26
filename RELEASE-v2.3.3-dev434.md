# BDC v2.3.3-dev434

Build: 3140  
Date: 2026-08-26  
Status: Development candidate

## Changes

- Replaces tall judge cards and visible secure URLs with compact live-status rows containing judge identity, Chief badge, marks, status, and aligned Copy, Open, Regenerate, and Reopen actions.
- Reduces each progress bar to a thin status line while retaining the existing two-second refresh.
- Replaces the extremely wide multi-judge criterion matrix with one tab per judge, showing all five criteria, judge total, and calculated place.
- Adds a compact All Judges Summary tab containing judge totals and calculated place.
- Aligns Save Checkpoint, Print Judge Sheets, Calculate & Sort Ranking, and Submit Scores & Lock button text and heights.
- Stacks judge actions predictably on mobile and preserves horizontal table safety for the detailed rubric.

## Parity Gate

- Testing Score Dashboard: shared Automatic workspace, isolated Test state, compact status rows, judge tabs, live refresh, and mobile CSS statically verified.
- Live Scoring Dashboard: the same shared Automatic workspace, real-data state path, aligned controls, judge tabs, and live refresh statically verified.
- Live Scoreboard / projector: no feed, projection control, audience rendering, refresh rate, or scoring calculations changed.

## Validation

- Candidate/static: focused v434 UI regression, full available Node regression suite, and `git diff --check`.
- PHP syntax/runtime: Not Runtime-Tested locally because PHP CLI is unavailable.
- Staging/runtime: Not Runtime-Tested. Production remains blocked until this exact commit passes desktop and mobile Test/Live browser checks, live judge refresh, calculation, submit/lock, printing, and projector sanity on Staging.

## Migration

- None. No stored judges, marks, criteria, results, or projector state are changed.

## Deployment

- Candidate intended for the GitHub `develop` release line only. Production is untouched.
