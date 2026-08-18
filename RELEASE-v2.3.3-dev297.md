# BDC 2.3.3-dev297 · Build 3003

## Festival Projection Hotfix

- Fixes `SQLSTATE[HY093]: Invalid parameter number` when creating or regenerating a Festival Live Projection.
- Uses separate native PDO placeholders for `event_id` and `active_event_id` while binding both to the same selected anchor event.
- Audited the remaining new festival-session INSERT statements; their placeholders are unique and correctly bound.
- Keeps the dev296 multi-event selection, Holding Screen safety, Test/Live isolation and brighter bounded fireworks unchanged.

## Validation

- Static SQL binding regression added to `tests/festival-live-projection-v296.php`.
- `VERSION.json` validation and repository diff checks pass.
- Staging must confirm that selecting two or more events creates the festival projection without HY093.

## Deployment

- Candidate branch: `develop`
- Production: not deployed and not modified
