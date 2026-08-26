# BDC v2.3.3-dev432

Build: 3138
Date: 2026-08-26

## Dance Cup open, live status and deletion safety

- Replaces Set Up & Open, Set Up Automation, Open Manual Sheet and Open Projection card actions with the same simple **Open** label used by Jack & Jill.
- Refreshes the Automatic scorer's all-judge status and live score matrix every two seconds without reloading or moving the page.
- Reports judges as complete when every required mark exists; individual Judge Submit remains optional until the administrator performs the final lock.
- Keeps a manual **Refresh Now** action, last-updated timestamp and hidden-tab polling pause.
- Shows Delete Draft only while a category is still Draft.
- Rejects direct deletion access for submitted scoring and transaction-locks the category before removing any dependent data, preventing a submission/deletion race.
- Applies the same shared service, card wording and live client behavior to isolated Testing and Live Dance Cup workflows.

## Parity Gate

- Testing Dance Cup: shared `bdc_test_dance_cup_*` service path, Automatic workspace, Manual card and protected deletion branch checked statically.
- Live Dance Cup: shared `bdc_dance_cup_*` service path, Automatic workspace, Manual card and protected deletion branch checked statically.
- Projector: no projector payload, command or rendering behavior changed; existing Dance Cup projector parity checks retained.
- Candidate/static validation: JavaScript integration, Test/Live shared-path, projector parity, version and diff checks passed. PHP CLI lint was unavailable in the local container and remains a runtime gate.
- Staging/runtime validation: not runtime-tested; Production promotion remains blocked until this exact commit passes Staging.

## Migration and deployment

- Database migration: none.
- GitHub `develop`: pending branch reconciliation and candidate publication.
- Staging: not deployed.
- Production: not deployed and not approved.
