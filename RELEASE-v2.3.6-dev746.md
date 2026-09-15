# BDC 2.3.6-dev746 — Published Archive Refresh Binding Correction

## Problem fixed

On an already-published standard Jack & Jill competition, the original approval modal remained in the document alongside the new detailed archive refresh form. Both controls reused the same readiness and progress element IDs. The archive JavaScript selected the hidden approval button first, so clicking the visible refresh button submitted immediately and PHP correctly rejected it because detailed Heats, Final and Points files had not been generated.

## Safe correction

- Gives the approval and refresh readiness fields unique DOM IDs.
- Gives approval and refresh progress messages unique DOM IDs.
- Prioritizes the visible published refresh button when that control exists.
- Finds the readiness field inside the form actually being submitted.
- Prevents submission unless the active form, readiness field and progress message are all available.
- Preserves the original approval and first-publication workflow.
- Preserves the existing archive backups, checksum audit and automatic restoration behavior.

## Data boundary

This correction changes browser form binding only. It does not modify scoring, Final rosters, placements, points, publication records, repository URLs or projector data.

## Parity Gate

- **Testing Score Dashboard:** the isolated standard Test publisher uses the corrected active-form binding.
- **Live Scoring Dashboard:** the Live standard publisher uses the identical corrected binding.
- **Live Scoreboard / projector:** inspected and unchanged because this correction only controls repository HTML generation before refresh.
- **Staging/runtime:** not yet tested. Production promotion remains blocked until this exact candidate completes the published refresh flow on Staging.

## Validation

- Extended `tests/standard-result-archive-refresh-v745.js` to reject duplicate IDs and require active-form-scoped readiness state.
- Focused detailed archive, detailed Heats and detailed Final regression checks must pass before push.
- PHP and browser runtime verification remains required on Staging before Production promotion.

## Deployment boundary

Push dev746 to `develop` for Staging/Test first. Production remains untouched until the corrected refresh flow is verified on Staging.
