# BDC v2.3.3-dev316

Build 3022 · Development release · 22 August 2026

## One judge sheet across Scoring Rounds

- Adds clear Round 1, Round 2 and onward tabs to Live and isolated Test judge scoring.
- Judges can switch between rounds independently instead of waiting for the organiser to advance Projection.
- Keeps one judge session and one final Submit & Lock action for the complete competition.
- Preserves saved marks while switching tabs.
- Calculates YES and A1/A2/A3 limits across all competitors, not separately per round.
- Validates the complete combined score sheet before submission.
- Blocks forged score requests for competitors outside the selected round.
- Leaves the organiser's active round as the single control for Live Projection only.

## Scope

- Test and Live scoring are updated together.
- Source release only on `develop`; Production and `config.php` are unchanged.
