# BDC 2.3.3-dev442

## Navigation separation

- Gives Jack & Jill its own admin sidebar group.
- Gives Dance Cup its own admin sidebar group containing Scoring, Participants and Approvals.
- Keeps Live Projection, Events and Registrations under shared Live Operations.
- Moves Scoring Backups into Operations Safety so it is not presented as belonging to either competition system.
- Does not change scoring logic, database records, permissions or result publication.
- Adds a self-contained style fallback to the Dance Cup Automatic workspace so it remains usable if the surrounding shell or external stylesheet fails.
- Replaces the Dance Cup Participants migration HTTP error with a normal recovery page confirming that data was not deleted.

## Validation

- Separate-scoring navigation regression updated and passed.
- Complete JavaScript and PHP static regression suites.
- `git diff --check`.
