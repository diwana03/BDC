# BDC 2.3.3-dev390

Build 3096 · 24 August 2026 · Development release

## Dance Cup Live Projection

- Rebuilds the Dance Cup audience projector using the proven Jack & Jill projection pattern.
- Opens Live Projection from the Dance Cup score sheet in a separate tab.
- Adds a safe launch gateway: every copied or newly opened projector link returns the audience screen to Holding before content is released.
- Preserves one event-wide projector link while keeping category switches atomic and isolated; switching category forces Holding.
- Adds premium venue-ready Holding, Contestant Call, All Contestants, Judges, Scoring Progress, Live Scoreboard and Winner Podium screens.
- Repaints the audience screen when marks, judge progress, active contestant or results change, without requiring a manual browser refresh.
- Adds remote page selection, optional automatic paging and 5–30 second page timing.
- Adds responsive landscape and portrait layouts, clear empty states, hidden-tab polling pause and 60-second HTTP 429 backoff.
- Keeps podium presentation free of automatic confetti, fireworks or other effects.

## Testing / Live parity

The same implementation is used in isolated Testing (`data_mode=test`) and Live tables. The capability token, active category, projector state, pages, judges, competitors and results remain environment-isolated.

## Database

Additive self-provisioning adds `page_number`, `auto_page` and `page_delay` to the existing Dance Cup event projection state table. No manual migration is required.

## Validation

- Dedicated Dance Cup projection parity/static regression test
- Safe-launch and category-switch Holding assertions
- Test/Live prefix and shared-code assertions
- New-tab score-sheet link assertion
- Live data repaint, paging, hidden-tab and HTTP 429 assertions
- Automatic podium-effect exclusion assertion

PHP CLI is not available in the release workspace, so staging browser/runtime validation remains required before Production.
