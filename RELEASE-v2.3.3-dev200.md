# BDC 2.3.3-dev200

## Automated Final judge links

- Fixes both Automatic Testing loaders so they recognize `finalScoreForm` as well as `heatsScoreForm`.
- Loads secure judge browser URLs for Test Final Relative Placement scoring.
- Replaces manual Final rank entry with the automatic judge panel when Automatic Testing is selected.
- Keeps Final judge setup and confirmed-couple pairing visible above the automatic panel.
- Adds the secure judge-link, sharing, progress and rescore panel to automated Live Finals.
- Reuses the existing Final browser-scoring implementation, criteria acceptance and Relative Placement marks.
- Random score generators remain Testing-only and do not appear in Live.

## Validation

- JavaScript syntax and repository whitespace checks passed.
- Test and Live both use their existing isolated judge-session services and tables.
- No Relative Placement calculation rules were changed.
- PHP CLI is unavailable locally; Staging health checks remain the PHP validation gate.

## Deployment

- Source release only. Deploy dev200 to Staging through Release Manager.
- Production was not deployed or modified.
