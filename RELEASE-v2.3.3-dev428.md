# BDC v2.3.3-dev428

Build: 3134  
Date: 2026-08-26

## Mobile-friendly administration

- Replaces the full-length mobile Dashboard sidebar with an accessible slide-out Menu, backdrop, Escape-key close and automatic close after navigation.
- Converts Competitor Management and Judge Directory rows into readable labeled cards on phones while preserving desktop tables.
- Stacks crowded management actions and narrow forms, increases touch targets and prevents page-level horizontal overflow.
- Applies safe momentum scrolling and compact responsive controls across the shared Dance Cup and scoring surfaces without changing scoring calculations or data.
- Preserves the desktop layouts above the mobile breakpoints and respects reduced-motion preferences.

## Validation

- Mobile Dashboard navigation regression: passed.
- Competitor and Judge mobile-card regression: passed.
- Shared Dance Cup/scoring mobile-layer regression: passed.
- Existing JavaScript regression suite: passed.
