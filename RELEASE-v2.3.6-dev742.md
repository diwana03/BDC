# BDC 2.3.6-dev742

## Special-category Final archive repair

- Archives the complete reviewed Final report at approval time, including finalist identities, bibs, judge rankings, relative-placement trail, Chief Judge and witnesses.
- Keeps “Readable Pages” and “Landscape, All Judges” inside the same authorized repository Final file; the landscape view prints as A3 landscape.
- Adds a Super Admin “Refresh Final Archive” action for already-published special-category results. It creates a protected server-side copy first and replaces only the static Final HTML; scores, rankings, points and publication records remain unchanged.
- Makes protected HTML result files revalidate on every open so a refreshed archive is visible immediately.

Production data is not modified by deployment. An existing published Final changes only when Super Admin explicitly runs “Refresh Final Archive.”

## Parity Gate

- Candidate/static — PASS: Live special-category publication (`admin/scoring/special-publish.php`), the shared Live Final report renderer (`admin/scoring/final-result.php`), protected upload endpoint (`admin/scoring/client-html-upload.php`) and public repository delivery (`result-file.php`) were traced and checked together.
- Testing dashboard — PASS, unaffected: the isolated Test publication and Final report paths remain unchanged; the full regression suite covered their existing parity checks.
- Live projector — PASS, unaffected: no projection feed, command, reveal state or display asset changed.
- Staging/runtime — NOT RUNTIME-TESTED: deploy this exact `develop` candidate to Staging, approve or refresh a special Final, open both archive layouts, print the landscape view, and confirm scores/points remain unchanged.
- Production — BLOCKED until the exact Staging commit passes the runtime checks above.

## Validation

- Focused v742 regression: PASS.
- JavaScript/static suite: 273 PASS; 2 environment-blocked wrappers require the unavailable PHP CLI.
- PHP syntax/runtime: NOT RUNTIME-TESTED because PHP CLI is unavailable in this workspace.
- Browser end-to-end of the new Staging flow: NOT RUNTIME-TESTED until deployment.

## Migration and deployment

- Database migration: none.
- Deployment status: candidate only; Production untouched.
