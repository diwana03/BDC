# BDC v2.3.3-dev429

Build: 3135  
Date: 2026-08-26

## Dance Cup judge slider scoring

- Replaces small mobile number boxes with touch-friendly sliders on the shared Test and Live Dance Cup judge form.
- Shows each selected mark immediately beside its slider and a live total for every contestant.
- Keeps an untouched criterion visibly marked `Not scored`, so a default slider position is never mistaken for a completed mark.
- Requires judges to read and accept the independence, score-limit, review and submission-lock rules before scoring controls open.
- Adds accessible live notifications for rule acceptance, mark selection, draft saving, errors and final submission.
- Retains custom criterion maximums, decimal scoring, debounced autosave, complete-score validation and the existing scoring calculation unchanged.

## Validation

- Slider and live-value regression: passed.
- Mandatory judge-rule acknowledgement regression: passed.
- Test/Live shared-surface regression: passed.
- Existing JavaScript regression suite: passed.
