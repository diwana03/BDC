# BDC v2.3.3-dev64

## Hall of Fame Homepage Integration

- Removes the dev63 special-category leaderboard filters. Bachata Rising, Bachata Open and Bachata Invitational do not have separate progression leaderboards.
- Special-category points continue to be awarded only into the competitor's normal BDC progression bucket.
- Adds `HallOfFameService` as the shared source for public Hall of Fame data.
- Portal Hall of Fame cards are now grouped by event and category, preventing results from different categories at the same event from being mixed.
- Hall of Fame cards display the official category label, including Bachata Rising, Bachata Open and Bachata Invitational.
- Adds public read-only JSON endpoint at `/api/hall-of-fame/`.
- Adds WordPress-ready Hall of Fame embed at `/hall-of-fame/embed/`.
- Embed defaults to special-category Hall of Fame results and supports `special=0` for all categories and `limit` for card count.
- Adds a WordPress Custom HTML iframe example under `integrations/wordpress/hall-of-fame-homepage.html`.
- No Release Manager or deployment workflow changes.
