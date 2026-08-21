# BDC v2.3.3-dev322

## Premium Production dashboard

- Replaces the generic bright-blue Production shell with a restrained BDC premium palette.
- Uses deep wine for the header, midnight charcoal for navigation, champagne gold for accents, and warm ivory for the workspace.
- Restyles navigation groups, active items, metric cards, quick actions, tables, system panels, and environment identification as one coherent interface.
- Keeps the official BDC logo on its required white background.
- Preserves the distinct amber Staging identity and all existing dashboard behavior.

## Workspace rule

- Records the mandatory workspace-first workflow in `AGENTS.md`.
- Defines R&D as workspace inspection, research, implementation, validation, versioning, commit, and publication to `develop` unless publication is explicitly declined.

## Validation

- Candidate/static: CSS selector and brace validation passed.
- Candidate/static: dashboard PHP parsed successfully.
- Candidate/static: `VERSION.json` parsed successfully.
- Candidate/static: whitespace validation passed.
- Database migration: not required.
- Staging/runtime: pending user deployment of this exact `develop` commit.
- Production: untouched; promotion remains user-controlled.

## Parity Gate

- Test dashboard: not functionally affected; no scoring workflow or selector changed.
- Live dashboard: not functionally affected; no scoring workflow or selector changed.
- Projector: not affected.
- Admin dashboard: `app/Views/admin/dashboard.php` and `public/assets/css/bdc-brand-theme.css` checked as the complete change surface.
