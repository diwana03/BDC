# BDC v2.3.3-dev407

Release date: 26 August 2026  
Build: 3113  
Branch: `develop`

## Complete unpublished-event cleanup

- Repairs both Bachata and Salsa profiles created only by unpublished Live or Test J&J rounds.
- Treats Draft, Completed, Awaiting Decision and Scores Submitted rounds as unpublished unless Super Admin publication created approved result/points history.
- Preserves published history independently for each dance style.
- Preserves matching manual, backup and official data-entry Special Category evidence.
- Clears the legacy Bachata division field when its unsupported profile is removed.
- Runs a new backed-up one-time deployment migration and records evidence for every deletion.

## Dance Cup isolation

- Confirms Dance Cup roster code does not write J&J discipline profiles.
- Draft and Submitted Dance Cup test cards therefore cannot create permanent J&J categories.

## Validation

- Full JavaScript regression suite.
- Focused cross-dance publication, recovery-evidence, legacy-field and Dance Cup isolation assertions.
- Repository diff and version/build checks.
- Staging deployment and data verification are mandatory before Production.
