# BDC v2.3.3-dev388

## Official Dance Cup score-sheet defaults

- Makes the supplied SBTA judgment sheet the default when creating a new Dance Cup category.
- Solo defaults to Timing 20, Musicality & Choreography 20, Difficulty 20, Dance Style Technique / Authenticity 20 and Overall Presentation 20.
- Couple and Duo default to Timing 20, Musicality & Choreography 20, Connection & Partnering 20, Dance Style Technique / Authenticity 20, Difficulty 10 and Overall Presentation 10.
- Team defaults to Timing 20, Musicality & Choreography 20, Synchronization & Teamwork 20, Dance Style Technique / Authenticity 20, Difficulty 10 and Overall Presentation 10.
- Every official template totals 100 points.
- Keeps the full custom option: organisers may rename, add, remove and reweight criteria before creating a category.
- Protects custom work when Entry Type changes; the new default is applied automatically only while the criteria remain unedited. A separate Apply Default button intentionally replaces the current rows.
- Existing categories, criteria, judge marks, calculated totals and published results are untouched.
- No database migration. Production untouched pending Staging validation.

## Validation

- PASS: shared service contains exact Solo, Couple, Duo and Team templates.
- PASS: active category-creation screen loads templates from the shared service.
- PASS: custom criteria controls and custom-edit preservation remain active.
- PASS: Manual and Automatic judge screens continue reading criteria and maximums from the saved category.
- PASS: projection continues reading calculated total scores without a parallel formula.
- PASS: executable regression test covers active Testing/Live consumers.
- NOT RUNTIME-TESTED: PHP CLI is unavailable in this workspace.
- NOT RUNTIME-TESTED: form interaction, database persistence, judge scoring and projection require the exact commit on Staging.

## Parity Gate

- Testing Score Dashboard: candidate/static PASS through the shared Dance Cup creator with data_mode=test and isolated bdc_test_dance_cup_* tables.
- Live Scoring Dashboard: candidate/static PASS through the same creator and real bdc_dance_cup_* tables.
- Judge screens: candidate/static PASS; saved category criteria and maximums remain dynamic in Manual and Automatic scoring.
- Live Scoreboard/projector: candidate/static PASS; calculated total_score remains the sole projected score input.
- Production promotion is blocked until Staging creates and scores one Solo, Couple/Duo and Team category, confirms custom criteria persistence, and verifies judge and projector totals.
