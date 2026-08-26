# BDC v2.3.3-dev419

Build: 3125  
Date: 2026-08-26

## Multiple Special Category registrations

- Replaces the one-value Special Category model with a canonical multi-row registration table.
- Keeps Open and Amateur/Rising registrations simultaneously for the same BDC identity, dance style and role.
- Gwen Dang can remain one Bachata Follower identity with both Bachata Open and Bachata Rising badges.
- A competitor appears when filtering either of their registered Special Categories.
- Career progression and Novice/Intermediate/Advanced point history remain independent.

## Evidence migration

- Migrates existing separated Special Category values.
- Reconstructs all completed Open and Amateur registrations from form-sync payload evidence, including people registered in both forms.
- Retains applied recovery and backup evidence.
- Clears the retired one-value scalar after all evidence is copied to the canonical table.

## Connected workflows

- Competitor Management cards, filters, profile badges and CSV export support multiple categories.
- Competitor editor supports selecting multiple categories.
- Data-entry reconciliation no longer gives Open destructive precedence over Amateur.
- Duplicate merging preserves all category records.
- Public Results and competitor career profiles display every registered category.
- Migration 0600 receives exact dependency-checksum compatibility before its shared recovery service evolves.

## Validation

- Multiple Open and Amateur/Rising registration regression: passed.
- Form-sync evidence migration regression: passed.
- Multi-category filtering, display, editing and merge regressions: passed.
- Applied migration 0500 and 0600 checksum compatibility regressions: passed.
- Point breakdown and All Participants regressions: passed.
- PHP/database and browser runtime: not locally tested; required on Staging.

## Deployment

- Candidate published to develop only.
- Production promotion remains blocked until Staging confirms Gwen under both Bachata Open and Bachata Rising, one BDC identity, unchanged points and career level.
