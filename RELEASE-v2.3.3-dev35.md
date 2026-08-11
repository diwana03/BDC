# BDC v2.3.3-dev35

## Automatic Scoring Module

- Replaces the Automated Scoring setup placeholder with an operational scoring workflow.
- Accepts 0–100 numeric marks from every judge assigned to each competitor.
- Rejects missing, non-numeric and out-of-range marks and panels with fewer than three judges.
- Calculates each competitor's valid-judge average independently for Leaders and Followers.
- Resolves equal averages by judge majority and then the Chief Judge score.
- Holds unresolved callback-boundary ties for a recorded Chief Judge decision.
- Assigns the configured callbacks and the next three alternates automatically.
- Reuses the production Relative Placement workflow for Automatic Finals, including duplicate-rank validation.
- Requires authorized review before publishing results; points remain controlled by the existing publication workflow.
- Stores calculation version, method and manual tie decisions in the scoring audit trail.
- Updates the official result sheet to show numeric judge marks and Average for Automatic rounds.

## Safety and Compatibility

- No database migration is required.
- Manual Scoring behavior is unchanged.
- No Production or Staging deployment is included in this release.
