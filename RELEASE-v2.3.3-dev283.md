# BDC 2.3.3-dev283 · Build 2989

## Automatic Test Judge Live Scoring 404 repair

- Serves the Judge Live Scoring panel through `automatic-screen.php?panel=1`, using the Automatic Test page that is already confirmed reachable on shared hosting.
- Keeps the old direct panel endpoint as a compatibility fallback.
- Routes embedded panel forms and polling through the same gateway.
- Includes the server response detail in any future loading error instead of showing only an unexplained HTTP status.
- Adds regression coverage for the gateway, shared panel include, request order and form routing.

## Scope

- Test Automatic Scoring only.
- No database migration.
- No Live or Production scoring rules changed.
