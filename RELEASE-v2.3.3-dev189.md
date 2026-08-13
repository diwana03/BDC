# BDC 2.3.3-dev189

## Judge browser workflow

- Requires every judge to read and accept the BDC judging criteria before scoring.
- Records criteria version and acceptance time per judge and round.
- Adds a reusable View Criteria control during scoring.
- Displays Timing 20%, Technique 20%, Connection 20%, Musicality 20%, Presentation 10%, and Difficulty 10%.
- Explains tier-based Heats selections and Final Relative Placement rules.
- Adds the approved YES, NO, A1, A2, A3, LATER, Save, Submit, and Criteria colour system.

## Admin controls and sharing

- Adds authorised-admin Reopen Scoring to Testing and Live.
- Reopening retains all marks and keeps the existing secure judge URL active.
- Records who reopened the session, when, and the mandatory reason.
- Submitted judge browsers detect reopening and return to scoring automatically.
- Reduces judge status refresh to one second.
- Adds one-click WhatsApp and important-email sharing for secure judge links.
- Email subject and body are visibly prefixed IMPORTANT because browser `mailto:` links cannot set a reliable provider-specific priority header.

## Migration

- Adds migration `20260814_0200_judge_criteria_acceptance.php`.
- Adds criteria acceptance and reopen audit columns to Testing and Live judge sessions.
- Existing marks, submissions, tokens, and criteria acceptance are preserved.

## Validation

- Testing implementation completed before Live parity changes.
- JavaScript syntax and repository whitespace checks passed.
- Testing/Live criteria, reopen, status refresh, secure-link retention, and colour parity reviewed.
- PHP CLI is unavailable in the development container; PHP lint must run automatically during Staging migration/health checks.

## Deployment

- Source release only. Deploy dev189 to Staging first through Release Manager.
- Production deployment was not performed.
