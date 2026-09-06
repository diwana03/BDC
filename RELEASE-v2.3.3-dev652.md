# BDC v2.3.3-dev652 — Final projector polish

Build 3358

## Changes

- Adds bottom breathing room inside every Finalist Couples and Emcee Random Final Match dancer identity so the BIB line stays visibly clear of the card edge.
- On Final rounds only, judge scope is presented as `JUDGING ALL COUPLES`.
- Heats wording remains exactly `JUDGING LEADERS`, `JUDGING FOLLOWERS`, or `JUDGING LEADERS & FOLLOWERS` according to the existing scoring scope.
- Does not change scoring assignments, matching, pair generation, five-card responsive grid logic, BDC logo, or `BDC · Official Live Display` badge.

## Validation

- PHP 8.1 syntax check on the projector feed.
- Focused regression checks Final-only wording, unchanged Heats wording, BIB bottom spacing, five-card grid, logo and official badge.
