# BDC 2.3.3-dev459

## Dance Cup multi-category judging panels

- Adds organizer-managed judging panels for Salsa, Bachata, Team Choreography, Pro Am or custom disciplines.
- Groups multiple Automatic categories from the same Dance Cup event.
- Assigns the judge panel once and synchronizes it safely into every included category.
- Generates one shared secure link for each judge in the panel.
- Adds a category desk at the top of the judge link with Not Started, In Progress, Ready to Submit and Submitted states.
- Allows judges to move between categories and submit each category independently.
- Shows aggregate category completion in the panel manager.
- Uses Judge Database contact records when available.

## Isolation and safety

- Every category retains separate contestants, criteria, marks, totals, ranking and results.
- Projection, scorer lock and Super Admin approval remain category-specific.
- Only Automatic categories from one event and one Test or Live data mode can be grouped.
- Categories with existing marks cannot be moved into a new panel.
- A category can belong to only one active panel.
- The first panel judge becomes Chief automatically; the organizer can explicitly select another Chief before scoring starts.
- No Jack and Jill files or calculations changed.

## Database

- Additive migration `20260827_0500_dance_cup_judging_panels.php` creates isolated Live and Test panel tables.
- Runtime table guards prevent a partially applied deployment from producing an HTTP 500.

## Validation

- Full JavaScript/static regression suite passed.
- PHP runtime and migration validation remain required on Staging.
