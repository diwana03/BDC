# BDC 2.3.3-dev453 · build 3159

## Remove reusable-profile approval as a scoring roster gate

- Allows an authorised organiser or scorer to add any active BDC competitor to a Dance Cup scoring roster.
- Keeps submitted registration styles, formats and levels as reference data instead of mandatory event eligibility.
- Applies the same behaviour to Manual and Automatic Dance Cup assignment through the shared service.
- Retains archived-profile, missing-profile, duplicate-entry and Female Only or Male Only event protections.
- Changes no scores, rankings, results, registrations or database schema.

## Parity Gate

- Testing and Live Manual Dance Cup use the shared eligibility service.
- Testing and Live Automatic Dance Cup use the same shared eligibility service.
- Judge scoring and projector endpoints are unchanged.
- Jack and Jill scoring core remains unchanged and checksum-protected.

## Validation

- Focused roster-gate regression passed.
- Complete JavaScript regression suite passed.
- PHP/browser runtime remains required on Staging.
