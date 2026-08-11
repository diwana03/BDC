# BDC v2.3.3-dev46

## Integrated Special Categories

- Removes Special Categories as a separate scoring mode from the scoring-mode selector.
- Manual Scoring and Automatic Scoring now both use the same Division dropdown with:
  - Novice
  - Intermediate
  - Advanced
  - Bachata Rising
  - Bachata Open
  - Bachata Invitational
- Special categories use the same Manual or Automatic scoring engine, round progression and Relative Placement Final workflow as standard divisions.
- Only the point-publication rule changes for special categories.
- Bachata Rising fixed points: 1st 5, 2nd 4, 3rd 3, 4th 2, 5th 1.
- Bachata Open fixed points: 1st 5, 2nd 4, 3rd 3, 4th 2, 5th 1.
- Bachata Invitational fixed points: 1st 3, 2nd 2, 3rd 1.
- Fixed points are recorded into the competitor's existing role-specific BDC Novice, Intermediate or Advanced progression bucket.
- BDC ID creation, identity matching and the normal competitor sequence are unchanged.
- Existing old `?mode=special` bookmarks redirect into Manual Scoring for compatibility.
- The old dedicated special-category screen is no longer part of the normal workflow.

## Safety

- Standard Novice / Intermediate / Advanced scoring calculations are unchanged.
- Release Manager workflow is unchanged.
- No Production code path is modified by this feature.
