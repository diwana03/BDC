# BDC v2.3.3-dev398

Release date: 25 August 2026
Build: 3104
Branch: `develop`

## Dance Cup workflow isolation

- Stores Manual or Automatic scoring on each new Dance Cup event and category.
- Shows a category only inside its saved scoring workflow and rejects mismatched direct URLs server-side.
- Adds a dedicated Automatic setup screen for contestants and judges before secure judge links are generated.
- Stops Manual status refresh from silently creating Automatic judge sessions.
- Keeps Live Projection available to both modes while each category retains its own roster, marks, results and lock state.
- Preserves every existing Dance Cup creation field, editable scoring criterion and calculation rule.

## Compatibility and migration

- Applies the same additive columns to isolated Test and Live Dance Cup event/category tables.
- Existing categories default to Manual; categories with judge sessions that were actually opened or submitted remain Automatic. Untouched sessions accidentally created by old Manual polling do not reclassify a category.
- New Automatic events and categories must be created from the Automatic workflow, matching the Jack & Jill choose-workflow-first pattern.

## Mandatory sanity gate

- `node tests/dance-cup-workflow-isolation-v398.js`
- Existing Dance Cup, permanent-division and repository gates must remain green.

## Deployment

- Migration: `20260825_0200_isolate_dance_cup_scoring_modes.php`.
- Staging runtime validation: **required on the exact release commit**.
- Production: **blocked until Staging validates Manual creation/scoring, Automatic setup/judge links, mode rejection and shared projection**.
