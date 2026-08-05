# BDC 2.3.3-dev5

## Release Manager reliability

- Accepts a Production deployment that the background worker has already moved from queued to running.
- Prevents direct Staging reconciliation from changing release state during an active Production deployment.
- Preserves the exact commit's Ready for Production state after failed or stale Production attempts.
- Keeps the failed deployment attempt and its diagnostic output visible for troubleshooting and retry.
