# BDC 2.3.3-dev278 · Build 2984

## Bachata Rising global naming

- Renames the former `BDC Rising Star` display label to `Bachata Rising` throughout the portal.
- Updates scoring dashboards, special-category scoring, draft editing, competitor administration, public registration, judge administration, judge registration, and eligibility guidance.
- Keeps the internal category key `bachata_rising` unchanged, preserving every existing round, competitor profile, score, and publication relationship.

## Parity Gate

- **Testing Score Dashboard:** canonical service label, generated category options, saved-round labels, and Test special-category validation checked.
- **Live Scoring Dashboard:** create-round selector, draft editor, special-category workflow, competitor surfaces, and judge surfaces checked.
- **Projector / Live Scoreboard:** raw `bachata_rising` label formatting and canonical service-driven labels both resolve to `Bachata Rising`; no data-key change required.

## Validation

- Candidate/static: JSON validation, whitespace validation, global legacy-label scan, canonical-label scan, and Test/Live/projector source parity completed.
- PHP CLI syntax/runtime: unavailable in the local workspace; must run during Staging deployment.
- Staging/browser runtime: pending deployment of this exact `develop` commit.
- Production promotion: blocked until Staging runtime validation passes.

## Migration and deployment

- Database migration: none.
- Production deployment: not performed.
