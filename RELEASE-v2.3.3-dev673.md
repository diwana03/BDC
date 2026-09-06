# Release v2.3.3-dev673

## WDC replacement photo upload repair

- Optimizes large JPG, PNG, and WebP files in the browser before upload so the request remains below common PHP request limits.
- Reports the real PHP upload failure instead of incorrectly saying that no photo was selected.
- Keeps the replacement isolated to the WDC identity; shared BDC and SDC photos remain unchanged.
- Preserves the existing crop, zoom, and adjusted-photo workflow.

## Database

No migration.
