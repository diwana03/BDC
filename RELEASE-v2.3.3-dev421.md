# BDC v2.3.3-dev421

Build: 3127  
Date: 2026-08-26

## Dance Cup Automatic database search

- Adds the official BDC header and logo treatment to the Automatic setup page.
- Adds live database suggestions while typing a competitor name or BDC ID.
- Adds live Judge Database suggestions while typing a judge name or Judge ID.
- Selecting a suggestion stores the canonical competitor or judge link, not only copied display text.
- Revalidates every selected profile on submission and blocks duplicate profile assignments.
- Still permits a genuinely new contestant, team or judge name when no directory profile exists.

## Test and Live parity

- The same Automatic setup implementation serves isolated Test and real Live Dance Cup categories.
- Test entries remain in `bdc_test_dance_cup_*` tables; Live entries remain in `bdc_dance_cup_*` tables.
- Both modes search the canonical BDC Competitor and Judge databases.
- Manual and Automatic Dance Cup setup now load the same current directory client.
- Scoring marks, calculations, judge links, completion and projector output are unchanged.

## Validation

- Automatic competitor and judge directory integration regression: passed.
- Canonical ID persistence and duplicate blocking regression: passed.
- Test/Live table isolation regression: passed.
- Shared BDC header integration regression: passed.
- All 54 locally executable Node regressions: passed.
- PHP syntax checks: not locally run because PHP is unavailable in this workspace; required on Staging.
- Browser/database runtime: not locally tested; required on Staging.

## Parity Gate

- Testing Dance Cup Automatic setup: candidate/static validation only.
- Live Dance Cup Automatic setup: candidate/static validation only.
- Testing and Live judge links/scoring: unchanged; existing static regressions passed.
- Projector controls and audience screen: unchanged; existing static regressions passed.
- Staging runtime validation remains required before Production promotion.

## Deployment

- Candidate committed locally after all locally available static checks passed; GitHub publication requires explicit user authorization.
- Production promotion remains blocked until Staging confirms the BDC header and both database suggestion menus work in Test and Live.
