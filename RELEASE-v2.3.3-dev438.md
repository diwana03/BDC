# BDC 2.3.3-dev438

## Profile Request review

- Adds a checkbox to every actionable Profile Request.
- Adds Select All, Approve Selected and Reject Selected.
- Reuses the existing single-request backend for every selected request, preserving validation, database transactions and audit records.
- Processes requests sequentially to prevent overlapping writes and reports completed versus failed requests.
- Failed requests remain unchanged for individual correction.

## Scroll restoration

- Remembers the exact Profile Requests page position during refresh and form submissions.
- Restores the position after individual or bulk review instead of returning to the top.
- Keeps scroll state isolated by page and active status filter and expires stale state after three minutes.

## Validation

- Complete JavaScript regression suite passed locally.
- Active integration, CSRF forwarding, individual-handler reuse, partial-failure reporting and scroll lifecycle assertions passed.
- PHP and browser runtime remain not tested until Staging deployment.
- Production promotion remains blocked until the exact Staging commit passes runtime validation.
