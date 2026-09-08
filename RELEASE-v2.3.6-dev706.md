# BDC v2.3.6-dev706

## Final Full Results projector repair

- Keeps the Couple `<td>` in the browser's table layout and moves the three-column couple grid into an inner wrapper, preventing competitor names from collapsing to zero width.
- Shows no more than eight judges per page and connects Final Full Results to the existing page count, Auto Page timer, and advance endpoint.
- Enlarges Final place, BIB, flag, competitor, judge, and mark presentation while preserving the 10% vertical and 5% horizontal projector safe area.
- Displays `NR` for a judge's legitimate unselected Top-N couples and explains the abbreviation on screen; scoring values and Relative Placement results are unchanged.
- Removes a trailing `_TEST` or `TEST` suffix from the audience event title while preserving the prominent TEST MODE badge.
- Refreshes the projection if Final marks or Final result rows change.

## Scope

BDC shared Test and Live Final Full Results projection only. No SDC/WDC scoring or result logic is changed.

## Validation

- JavaScript structural regression test for pagination, markup, responsive presentation, `NR` semantics, state refresh, controller wiring, and release metadata.
- Browser geometry test for 1363×936 landscape and 1080×1920 portrait runs when Playwright Chromium is available; the local Work Mode runtime does not include that browser binary.
- PHP syntax validation is required in the Test deployment environment because PHP is unavailable in the local Work Mode runtime.
