# BDC v2.3.3-dev418

Build: 3124  
Date: 2026-08-26

## Competitor point clarity

- Replaces the single Bachata/Salsa total in Competitor Management with a clearly labelled total and division breakdown.
- Shows Novice, Intermediate and Advanced points independently for both Bachata and Salsa.
- Example: Melissa Jane displays Bachata Total 52, Novice 26, Intermediate 26 and Advanced 0.
- Keeps current progression level and Special Competition Category in the separate profile column.
- Calculates every bucket directly from the style-specific point transactions, without changing scores or progression.

## Validation

- Bachata division aggregation regression: passed.
- Salsa division aggregation regression: passed.
- Required total and division labels regression: passed.
- All Participants dashboard card regression: passed.
- VERSION.json validation: passed.
- Browser runtime: not tested locally; verify after Staging deployment.

## Deployment

- Candidate published to develop only.
- No database migration or scoring calculation is changed.
