# BDC 2.3.3-dev192

## Testing dashboard response hotfix

- Fixes the Testing scoring dashboard displaying compressed response bytes as unreadable characters.
- Adds the same explicit UTF-8 HTML response type used by the Live scoring dashboard.
- Allows Bluehost to advertise its actual gzip or Brotli encoding so the browser decodes the page correctly.
- Adds `no-transform`, `no-store`, and private cache handling for the Testing scoring page.

## Safety

- Does not change scoring calculations, competitors, judges, marks, results, or database tables.
- Keeps Testing isolated from Live.
- Does not force `Content-Encoding: identity`, which previously caused this exact shared-hosting problem.

## Validation

- Confirmed the affected Testing entry page lacked the response safeguards already present on Live.
- Repository whitespace and JavaScript syntax checks passed.
- PHP CLI is unavailable locally; Staging health checks remain the PHP validation gate.

## Deployment

- Source release only. Deploy dev192 to Staging through Release Manager.
- Production was not deployed or modified.
