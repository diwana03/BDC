# BDC v2.3.3-dev143

Build 2473 · Development release · 13 August 2026

## Automatic Testing round completion

- Added **Preview Scores / Calculate & Sort** using the existing Test scoring calculation.
- Added **Submit Scores** and **Preview / Print Draft Result**.
- Added callback routing from Heats to Semifinal or directly to Final.
- Added callback routing from Semifinal to Final.
- Prevented next-round creation while callback ties remain unresolved.
- Automatically reopens the Automatic Testing dashboard on the newly created round.
- Preserved existing scoring witnesses and scoring administrator values.

## Scope

- Testing dashboard only.
- Uses the same Test scoring and callback engine as Manual Scoring.
- No Production deployment or configuration changes.
