# BDC v2.3.3-dev330

## Delete multiple backups

- Adds row checkboxes and Select All to shared Test and Live scoring checkpoint history.
- Adds one Delete Selected form with a required reason and typed `DELETE SELECTED` confirmation.
- Keeps every scoring checkpoint restricted to the selected round and Test/Live data mode and records the existing deletion audit for each checkpoint.
- Adds the same checkbox, Select All and Delete Selected workflow to Available Recovery Backups on the Super Admin Backup Dashboard.
- Validates every selected portal backup through the existing exact backup type/name resolver before deletion.
- Preserves individual Restore, Apply, Download and Delete controls.

## Validation

- Candidate/static: bulk action allowlists, permission gates, CSRF fields, typed confirmation, empty-selection validation, exact IDs/type-name values and shared Test/Live panel markers passed.
- Candidate/static: version JSON and whitespace checks passed.
- Database migration: not required.
- PHP runtime: unavailable in the development container; browser execution remains pending on Staging.
- Production: untouched; promotion remains user-controlled.

## Parity Gate

- Test dashboard: isolated bulk checkpoint deletion handler checked.
- Live dashboard: matching bulk checkpoint deletion handler checked.
- Shared backup panel: checkbox selection, Select All, reason and confirmation form checked.
- Central scoring recovery and portal Backup Dashboard: bulk deletion handlers checked.
- Projector: not affected; no projection, scoring, ranking or publication logic changed.
- Staging/runtime: pending user deployment and browser validation; Production promotion remains blocked until that passes.
