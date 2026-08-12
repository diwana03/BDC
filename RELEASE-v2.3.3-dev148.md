# BDC v2.3.3-dev148

Build 2478 · Development release · 13 August 2026

## Scoring print preview

- Adds a **Landscape, All Judges** preview alongside the existing readable-page layout.
- Covers Heats, Semifinal when used, and Final rounds.
- Applies the same options to both Testing and Live scoring dashboards.
- Works with both Manual and Automatic scoring because they share the result preview pages.
- Keeps Leader and Follower judge trails separate in Heats and Semifinal reports.
- Uses a wide, horizontally scrollable screen preview and A3 landscape print layout for large judge panels.

## Workflow cleanup

- Removes the duplicate Callback Destination panel from Automatic Testing scoring.
- Keeps the original Next Round workflow as the single place to send callbacks to Semifinal or Final.

## Deployment scope

- Development branch release only.
- No Production deployment or Production configuration change.
