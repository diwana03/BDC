# 2.3.3-dev455

- Fixes saved Dance Cup judge marks appearing only after a full browser refresh.
- Runs one uncached live-state request at a time so slow responses cannot overlap or overwrite newer matrix state.
- Retries every two seconds after temporary failures and displays connection recovery status.
- Preserves the current page position because the document is never reloaded.
- Applies to Test and Live without changing marks, formulas, Jack and Jill or the database schema.
