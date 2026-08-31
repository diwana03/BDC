# v2.3.3-dev539

- Adds a signed, five-minute request window for bulk Jack & Jill competitor and judge profile submissions without an Admin browser session.
- Stages every submission for review and keeps the existing Google Form endpoint compatible while preventing it from writing live competitor profiles directly.
- Adds separate Competitor and Judge review tabs with filters, select-all, bulk approval, bulk rejection and explicit resolution of ambiguous identities.
- Preserves exact JPG, PNG and WebP bytes in protected staging and after approval; the API never crops, resizes, rotates or re-encodes photos.
- Applies each approved item in an independent database transaction, preserves both Amateur and Open registrations and does not change earned permanent division progression.
- Explicitly excludes points, results, placements, scoring, leaderboards, payments, user accounts and permissions from the integration service.
- Adds the `20260831_0100_profile_integration_review` schema migration and API setup documentation.
- Adds a focused executable regression check covering authentication, staging, deduplication, photo integrity, bulk review and forbidden points/scoring access.
- PHP CLI lint and Staging runtime verification remain pending because PHP and a deployed authenticated environment are unavailable in this workspace.
