# BDC v2.3.3-dev49

## Release Manager Staging job lifecycle fix

- Fixes the Recent Activity pattern where one Staging deployment could appear first as **Needs Retry** and then as **Success** a few seconds later.
- A legitimate queued/running Staging worker job now owns the candidate state as `deploying` until that same job reaches a terminal result.
- The legacy direct-deployment reconciliation path can no longer reinterpret an active web-triggered Staging deployment while it is still running.
- A successful Staging job moves its exact release candidate to `passed` and records the exact tested commit SHA.
- A genuinely failed or stale Staging job moves the candidate to `failed` normally.
- The existing global active-job check continues to block a second deployment while one is queued or running.

## Unchanged

- Production promotion workflow and safety chain.
- Manual and Automatic scoring engines.
- Automatic Judge Browser Scoring V1.
- Registration Desk and BDC ID logic.
- Special-category fixed point logic.
