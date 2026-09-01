# BDC v2.3.3-dev547

## Fix

- Fixes the PHP 8 parse failure in the Dance Cup Automatic workspace approval notice.
- Explicitly groups the pending-approval fallback ternary instead of using the PHP 8-invalid unparenthesized chained form.
- Routes the page through the corrected `dance-cup-automatic-workspace-v547.php` include so the full Judge Scoring workspace can render after roster confirmation.

## Root Cause Evidence

- Production `v2.3.3-dev546` loaded the new include path but emitted no mount marker or workspace HTML.
- The included file contained `approved ? approved_text : pending ? pending_text : draft_text`, which PHP 8 rejects while parsing the complete file.
- Because parsing happens before output, the marker at the start of the file could not render.

## Validation

- Focused regression asserts the corrected PHP 8 grouping and versioned include integration.
- Focused Automatic Dance Cup workflow regression: passed.
- Candidate static suite: 151 JavaScript checks with the same 28 failures as the unchanged baseline.
- PHP runtime confirmation: pending deployment because PHP is unavailable locally.
- Database migration: none.

## Parity Gate

- Testing Score Dashboard: corrected shared workspace with isolated Test data.
- Live Scoring Dashboard: corrected shared workspace with Live data.
- Projector: implementation unchanged; workspace links become available again.
- Runtime: pending deployment and direct marker, judge-progress and button-transition verification.

## Deployment Status

- Candidate only. Not deployed to Staging or Production.
