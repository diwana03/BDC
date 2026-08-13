# BDC 2.3.3-dev175

## Fail-safe admin navigation

- Builds the complete categorized sidebar separately before displaying it.
- Leaves the original full navigation untouched if any browser-side failure occurs.
- Prevents Release Manager and other administrative links from disappearing.
- Retains collapsible app-style categories and saved open/closed preferences.

## Deployment

- No database migration is required.
- Hotfix targets Staging through the `develop` branch.
