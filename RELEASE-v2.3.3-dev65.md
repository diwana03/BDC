# BDC v2.3.3-dev65

## Universal Admin Navigation

- Adds a consistent **Back** control to BDC Admin HTML screens.
- Adds a **Dashboard** control on admin sub-screens that always returns to the main BDC Admin dashboard.
- Keeps the existing public BDC Home link separate from Admin Dashboard navigation.
- Integrates into both the modern dashboard top bar and legacy Bootstrap admin navigation bars.
- Falls back to a small top-right navigation control on admin screens without a recognised top bar.
- Does not inject into JSON/AJAX endpoints, judge-control iframe, downloads, exports, streams or other non-HTML responses.
- No scoring, registration, leaderboard, points, database or Release Manager logic changed.
