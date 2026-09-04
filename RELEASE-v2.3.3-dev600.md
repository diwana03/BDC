# BDC 2.3.3-dev600

## Critical secure URL correction

- Removes recursive calls between the token-aware relative and absolute URL helpers.
- Prevents duplicated hosts such as `https://hosthttps//host/portal/...`.
- Produces one canonical HTTPS portal URL for Automatic Judge Scoring.
- Applies the same repair to Test Judge Scoring, judge profiles, stage displays and projection remotes.
- Does not rotate, delete or alter existing tokens, assignments, marks or scores.
