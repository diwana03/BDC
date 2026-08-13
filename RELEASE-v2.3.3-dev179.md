# BDC 2.3.3-dev179

## Competitor workflow continuity

- Preserves the complete Competitor Management filter state when opening Edit or Adjust Photo.
- Keeps search, country, dance style, role, division, status, missing-data filter, sorting, page, and rows per page.
- Returns directly to the same competitor row after an adjusted photo is saved.
- Carries the original list context between Bachata and Salsa profile tabs.
- Accepts only local query-string return destinations.

## Deployment

- No database migration is required.
- Targets Staging through the `develop` branch for validation first.
