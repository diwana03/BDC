# BDC 2.3.3-dev654

Build 3360

## Judge projector country renderer fix

- Fixes the dev653 multi-country judge projector issue at the final Live Display wrapper layer instead of relying only on cached projector CSS.
- Converts judge country labels to compact three-character country codes after each projector feed loads, including DOM, SWE, COL and ESP for the approved layout.
- Keeps all judge flags on one row with responsive spacing and smaller flags when four or five countries are present.
- Injects the judge country layout as the last projector style so older cached inline or external projector rules cannot restore overlapping full country names.
- Forces fresh projector roster and safe-area stylesheet cache keys for this release.
- Applies to both the full Judges board and one-by-one Judge Call while preserving names, photos, Chief Judge treatment, scoring scope, Test/Live parity and projector safe areas.

Branch: develop
