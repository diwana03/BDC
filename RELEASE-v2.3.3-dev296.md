# BDC 2.3.3-dev296 · Build 3002

## Multi-Event Festival Live Projection

- Adds **Multi-Event Festival Projection** to both Live and Test projector workspaces.
- Select two or more Jack & Jill events and create one named festival projection.
- Keeps one permanent projector link while the operator changes Event, Division and Round.
- Lists saved festival projections so the operator can reopen the shared controller later.
- Keeps ordinary single-event projection available and unchanged.

## Audience Reveal Safety

- Every active-event or round change forces the shared projector back to **Holding Screen**.
- Switching also clears effects, podium state, page position and projector loops.
- Server-side membership validation blocks a controller from selecting an event outside its festival.
- The public feed, score matrix and podium now resolve the active festival event instead of the session's anchor event.

## Fireworks Visibility

- Enlarges fireworks, improves spread and increases the visible burst cadence.
- Retains the optimized 260-particle cap, two-rocket limit and adaptive 24 FPS rendering.

## Migration

- `20260819_0120_festival_live_projection.php` adds the active event and festival name to projection sessions.
- Adds normalized festival-session event membership and backfills every existing projector session as a single-event session.

## Parity Gate

### Candidate/static validation

- Test Projection: uses the shared multi-event workspace with isolated `bdc_test_*` event and round tables.
- Live Projection: uses the same shared workspace with Live tables.
- Live Scoreboard/projector: feed, state, Final matrix and podium resolve `active_event_id`.
- Reveal safety: session event membership and Holding Screen reset are enforced server-side.
- Effects: fireworks remain bounded while visibility is increased.
- Static regression: `tests/festival-live-projection-v296.php`.
- PHP CLI is unavailable in this workspace; PHP syntax and migration execution remain gated by Staging health checks.

### Staging/runtime validation

- Create a Test festival with at least two events and keep the projector URL open.
- Project one slide, change to another event, and confirm the audience sees Holding Screen before the next slide.
- Repeat with Live Projection and verify event titles and scores never cross data modes.
- Trigger Cinematic Fireworks on a projector-sized display and confirm visibility without sustained lag.

## Deployment

- Candidate branch: `develop`
- Production: not deployed and not modified
