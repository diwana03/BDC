# BDC v2.3.3-dev218

## Judge Mobile Screen

- Makes the BIB identifier large and visually dominant on every scoring card.
- Reduces the competitor name size so judges can scan primarily by BIB.
- Separates the sticky YES, A1, A2 and A3 totals into dark green, gold, slate and burnt-orange blocks.
- Preserves the responsive three-column score buttons on smaller phones.

## Judge Link Lifetime

- New and regenerated judge links remain valid for 12 hours.
- Detects an expired stored judge session and replaces it with a fresh 12-hour link instead of displaying an unusable link.
- Existing scores and valid active sessions are preserved.

## Safety

- Changes are limited to the `develop` release line; Production is not modified.
