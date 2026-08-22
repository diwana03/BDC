# BDC v2.3.3-dev344 — Resolution-Aware Live Projection

## Audience projector

- Adds one shared responsive presentation layer to the Test and Live audience projector.
- Sizes event headings, round labels, competitor cards, bibs, photos, flags, score tables, judge keys and podium content from both the rendered stage width and height.
- Preserves the selected 16:9, 16:10, 4:3, 21:9, 32:9, 9:16, square or custom aspect ratio without stretching images or projection content.
- Adds safe stage insets for ordinary projectors and tighter horizontal insets for ultrawide LED walls.
- Stacks Leader and Follower panels vertically on portrait projection formats.
- Keeps short projector windows compact so headings do not consume the scoring area.
- Applies the same scaling to the standalone Final Relative Placement projection.

## Safety

- Presentation-only release: no scoring calculation, ranking, judge mark, submission, database or projector-session contract changed.
- No database migration and no configuration change required.
- Existing server-side layout density and automatic pagination remain intact.

## Parity Gate

- **Testing Score Dashboard:** shared Test projector route and isolated `data_mode=test` feed reviewed; no Test scoring mutation.
- **Live Scoring Dashboard:** shared Live projector route reviewed; no Live scoring mutation.
- **Live Scoreboard / projector:** `live-display/feed.php`, `live-display/final-relative-placement.php` and `public/css/projector-responsive-v344.css` updated together.
- **Candidate/static:** JSON validation, whitespace validation, shared asset loading and projection surface scan completed. PHP CLI is unavailable in the workspace, so PHP runtime execution is pending.
- **Staging/runtime:** pending deployment of the exact `develop` commit and full-screen checks at 1920×1080, 3840×2160, 1024×768, 2560×1080 and a portrait viewport.
- **Production:** not deployed. Promotion remains blocked until Staging runtime validation passes.
