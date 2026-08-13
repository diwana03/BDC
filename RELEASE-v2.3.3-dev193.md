# BDC 2.3.3-dev193

## Confirmed double-compression fix

- Direct server diagnostics confirmed Bluehost returned a cached binary body for Scoring Tests even with `Accept-Encoding: identity`.
- Disables PHP zlib/output-handler compression before the large Testing page is generated.
- Prevents Apache gzip for the authenticated Scoring Tests route without forcing a false `Content-Encoding` header.
- Adds route-level no-cache headers, including `X-Accel-Expires: 0`, for the Bluehost nginx/endurance cache.
- Routes the dashboard menu and Manual Test selection through the uncached `dashboard.php` entry point so the old poisoned cache object is bypassed immediately.

## Safety

- Keeps every Testing page authenticated and private.
- Does not change scoring logic, judges, competitors, marks, results, points, or database tables.
- Live scoring code and Live data are unchanged.

## Validation

- Reproduced the bad server response with curl before implementing this fix.
- Confirmed the old route was being served with `cache-control: max-age=7200` and binary content.
- Repository whitespace and JavaScript syntax checks passed.
- PHP CLI is unavailable locally; Staging health checks remain the PHP validation gate.

## Deployment

- Source release only. Deploy dev193 to Staging through Release Manager.
- Production was not deployed or modified.
