# BDC v2.3.3-dev409

Release date: 26 August 2026  
Build: 3115  
Branch: `develop`

## Read-only reconstruction

- Adds Competitors → Test Event Profile Evidence for only the exact named test events.
- Shows each participant/style, current profile, published evidence, manual/import history, Special Category recovery evidence and classification.
- Disables automatic deployment deletion when the system user runs historical repair migrations.
- Makes the missing pre-17 August baseline explicit instead of guessing or changing unrelated old data.
- Does not change event scoring, profiles, results, points, recovery history or competitors.

## Validation

- Full JavaScript regression suite.
- Focused read-only, exact-event scope and automatic-mutation-stop assertions.
- Repository diff and version/build checks.
- Review the Staging evidence report before authorizing any targeted correction.
