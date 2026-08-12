# BDC v2.3.3-dev157

Build 2487 · Development release · 13 August 2026

## Dashboard logo correction

- Replaces the clipped dark circular logo treatment with the complete BDC logo.
- Uses a clean white square tile for clear contrast against the bright blue Production header.
- Bumps the dashboard theme cache version so the corrected logo loads immediately.

## Bulk event archiving

- Adds a checkbox to each completed event on the dashboard.
- Adds Select All and a live selected-event count.
- Adds one Archive Selected action for all checked events.
- Validates that every selected record exists and remains Completed before making changes.
- Archives all selected events in one database transaction, preventing partial bulk operations.
- Keeps the previous individual event archive request format working.

## Deployment scope

- Development branch release for deployment through the web Release Manager.
- No direct Production deployment or Production configuration change.
