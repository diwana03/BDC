# BDC v2.3.3-dev315

Build 3021 · Development release · 22 August 2026

## Scoring Rounds

- Renames the user-facing Flight workflow to clear Scoring Round labels: Round 1, Round 2 and onward.
- Selecting an active Scoring Round now switches the connected Live Projection to the same round.
- Live Automatic judge pages show only competitors assigned to the active Scoring Round.
- Judge pages automatically refresh when the organiser advances the active round.
- Earlier saved marks remain attached to competitors and stale judge pages cannot overwrite the newly active round.
- Submission remains blocked until the organiser reaches the final configured Scoring Round.
- Preserves the existing internal flight tables and field names to avoid a risky database migration.

## Scope

- Applies to the shared Live Projection controller and Live Automatic scoring.
- Carries active-round filtering into isolated Test scoring.
- Source release only on `develop`; Production and `config.php` are unchanged.
