# BDC 2.3.3-dev374 — Readable Score Reports and Position Restore

## Improved

- Replaces the compressed two-column Heats report when there are eight or more judges with full-width, role-specific printable pages.
- Keeps up to 14 judge columns together before starting another judge group, so an 11-judge panel prints as one clear Leaders page and one clear Followers page.
- Preserves the exact scorer scroll position through Manual **Save Draft**, **Calculate & Sort**, and **Submit Scores** reloads.
- Applies the same behavior to isolated Testing and Live scoring.

## Safety

- No score values, weights, tier rules, callback ordering, tie rules, submission locks or automatic scoring calculations changed.
- The browser still completes the existing server round-trip; only the post-refresh position is restored.
- No database migration is required.

## Parity Gate

- **Testing dashboard:** `admin/scoring-tests/index.php`
- **Testing report:** `admin/scoring-tests/result.php`
- **Live dashboard:** `admin/scoring/core.php`
- **Live report:** `admin/scoring/result.php`
- **Projector:** not affected; no projected data, state or rendering changed.

Candidate/static validation is included in this release. Staging runtime validation remains required before Production promotion.
