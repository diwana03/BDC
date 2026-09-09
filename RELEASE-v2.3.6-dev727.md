# BDC v2.3.6-dev727

## Change

Permanently repairs `uq_scoring_bib` failures when an empty Jack and Jill round still contains recoverable withdrawn entries. A shared transactional entry lifecycle now restores the same withdrawn competitor or moves only the conflicting withdrawn bib to its private tombstone value before adding the requested active entry.

The unique bib constraint remains enabled. Events, rounds, scores and withdrawal history are not deleted.

## Affected paths

- Super Admin integration approval for competitor additions
- Super Admin integration approval for roster synchronization
- Live Automatic Scoring direct competitor addition
- Isolated Test and Live integration tables through one shared service

## Validation

- Focused executable regression check: Pass
- Existing cached withdrawal bridge regression: Pass
- Existing OAuth login-resume regression updated to accept dev725 and later release metadata
- Existing draft-addition regression updated to follow the active shared lifecycle service
- PHP syntax check: Not Runtime Tested because PHP is unavailable in the local Codex runtime
- Staging browser workflow: Not Runtime Tested until this exact candidate is deployed

## Migration

No database migration required.

## Parity Gate

- Testing Score Dashboard: shared isolated `bdc_test_scoring_entries` recovery path statically verified
- Live Scoring Dashboard: integration approval and direct Automatic addition paths statically verified
- Projector: inspected as not affected because the change only controls draft roster entry recovery before scoring
- Candidate static validation: partial pass, blocked only on unavailable local PHP runtime
- Staging runtime validation: pending
- Production promotion: blocked until Staging runtime validation passes

## Deployment

Production is unchanged.
