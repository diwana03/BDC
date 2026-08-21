# BDC 2.3.3-dev306 · Build 3012

## Scoring workflow selection

- The main Scoring Dashboard now offers two explicit workflows: **Jack & Jill** and **Dance Cup**.
- Existing Manual, Automatic and Live Projection controls remain inside Jack & Jill without changing their scoring logic.
- Dance Cup opens in its own isolated dashboard.

## Dance Cup foundation

- Creates numeric-scoring categories for Solo, Couple, Duo and Team entries.
- Supports Qualifier, Quarterfinal, Semifinal and Final category rounds.
- Provides editable criteria names and maximum marks with an automatically calculated total.
- Starts with the global-style 100-point template: Timing, Musicality/Choreography, Technique, Difficulty, Connection/Synchronisation and Presentation.
- Stores Dance Cup competitions and criteria separately from Jack & Jill rounds and points.
- Adds an isolated **Test Dance Cup Scoring** route using `bdc_test_*` events and Dance Cup tables.
- Creates all Live and Test tables only through the ordered migration; admin requests never mutate schema.

This release establishes category and criteria configuration. Entrant management, judge links, mark entry, calculation, penalties and publication will attach to this isolated Dance Cup workflow in the following implementation stages.

## Parity Gate

- **Testing:** `Scoring Tests Dashboard → Dance Cup Scoring` uses the shared dashboard with isolated Test events, competitions and criteria.
- **Live:** `Scoring Dashboard → Dance Cup` uses the same service and dashboard against Live tables.
- **Projector:** no Dance Cup projection is introduced in this foundation; existing shared Test/Live projection behavior was reviewed and remains unchanged.
- Static workflow, isolation, migration and release checks completed; staging runtime confirmation remains pending in Release Manager.
