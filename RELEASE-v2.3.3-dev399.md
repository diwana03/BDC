# BDC v2.3.3-dev399

Release date: 25 August 2026  
Build: 3105  
Branch: `develop`

## Emergency Special Category recovery

- Reverses the destructive profile-schema restriction introduced by dev397.
- Restores Bachata Rising, Bachata Open, Bachata Invitational, Salsa Rising and Salsa Open as valid manually managed competitor profile values.
- Reconstructs each competitor's latest intentional manual assignment from `competitor_created` and `competitor_updated` audit records, including competitors with no completed or published event.
- Restores Bachata legacy profile compatibility and the dance-specific discipline profile together.
- Never infers a manual assignment from scores, results, points or event entries.
- Records every candidate and applied recovery in `bdc_special_category_recovery` for review.
- Adds a Super Admin recovery report linked from Competitor Management.
- Keeps a pre-dev397 database backup as the required fallback for assignments older than audit logging.

## Data safety

- The migration is targeted to the `current_division` fields of audit-confirmed manual Special Category assignments.
- Names, IDs, roles, photos, contacts, scores, events, registrations, results, points and publications are not rewritten.
- Latest manual audit state wins; if an administrator later changed a competitor to a normal career division, the older Special Category is not restored.
- The recovery is idempotent and keeps an evidence row for every restored assignment.

## Parity gate

- Live competitor profiles and filters: restored and statically validated.
- Test competitor schema: Special Category values are accepted again; no Live data is copied into Test.
- Scoring/projector: no calculation, score, round, judge or projection code is changed.

## Validation and deployment

- Automated recovery gate: `node tests/special-category-manual-recovery-v399.js`.
- Full JavaScript regression suite required before publication.
- PHP runtime and database recovery counts: not runtime-tested in Codex.
- Migration: `20260825_0300_restore_manual_special_categories.php`.
- Production promotion is blocked until Staging reports the candidate count, restored count, skipped count and verifies known manually assigned competitors.
