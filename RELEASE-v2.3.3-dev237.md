# BDC 2.3.3-dev237

## Projector auto-page verification fix

- Defines the Test/Live scoring results table before Callback and Finalist role-count queries.
- Fixes the same issue in both projector state calculation and automatic page advance.
- Preserves dev236 round-aware projector controls and Live Score Matrix behavior.
- Heats-only projector controls remain hidden for Semifinal, Final and final-only events.

## Validation and deployment

- Verified the dev236 develop commit and affected projector files directly on GitHub.
- Static checks confirm both auto-page paths now initialize the correct Test or Live results table.
- PHP runtime and full-screen visual validation must be completed on Staging.
- No database migration or configuration change is required.
- Push target: `develop` only.
- Production deployment: not performed.
