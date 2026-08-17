# BDC 2.3.3-dev274 — build 2980

## Premium scoring dashboard redesign

- Introduces one shared premium theme across 17 scoring administration pages.
- Modernises Manual Scoring, Automatic Scoring and isolated Testing workspaces without changing scoring calculations or workflow rules.
- Adds refined navigation, layered page backgrounds, premium cards, stronger visual hierarchy and consistent status treatments.
- Improves scoring matrices with clearer headers, borders, hover states, callback, alternate and tie-state colours.
- Upgrades forms, buttons, alerts, badges, modals, sticky actions and role panels with consistent interaction states.
- Adds responsive spacing, touch-friendly controls and compact mobile tables for scoring operations on phones and tablets.
- Extends the same appearance through scoring history, special categories, Test tools, publication and approval screens.
- Preserves reduced-motion accessibility and maintains clear locked, disabled and emergency states.

## Parity Gate

- **Testing Score Dashboard:** Test mode selector, Test dashboard, Automatic simulator, parity report, data manager and Test publication workflow all load the shared premium theme.
- **Live Scoring Dashboard:** mode selector, active rounds, Manual dashboard, Automatic dashboard, special-category, history, deletion and publication workflows all load the identical theme.
- **Live Scoreboard / projector:** no projector presentation behavior or styling changed; this release is restricted to scoring administration interfaces.
- Complete scoring-chain behavior remains unchanged: setup, assignments, judge links, drafts, calculation, submission, completed state, ties, next rounds, print and Final workflows retain their existing code paths.
- Candidate/static validation: `git diff --check`, JSON parsing, 17-of-17 Bootstrap scoring-page theme coverage and Test/Live asset parity passed.
- Browser layout and shared-hosting PHP runtime remain pending Staging validation.
- Production promotion remains blocked until Staging confirms desktop and mobile scoring workflows remain functional.

## Deployment

- Database migration: none.
- Staging deployment: pending.
- Production deployment: not performed.
