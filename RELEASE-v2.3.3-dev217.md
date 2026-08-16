# BDC v2.3.3-dev217

## Full Scoring Workflow Repair

- Final Leader search now lists only Leader BDC IDs; Final Follower search lists only Follower BDC IDs.
- Automatic qualifier and Final results cannot be calculated or submitted until every judge has submitted and locked their session.
- Automatic Final calculation reads the ranks already saved from judge live links without requiring duplicate manual entry.
- Final publication always passes through the dance/category gate for Bachata, Salsa and fixed-point categories.
- Automatic judge-save and special-tier actions now return visible success or error feedback on the correct dashboard.

## Safety

- Existing competitors, judges, links, marks, pairings and results are preserved.
- Manual scoring remains available and follows the same Final and publication routing.
- Changes are limited to the `develop` release line; Production is not modified.
