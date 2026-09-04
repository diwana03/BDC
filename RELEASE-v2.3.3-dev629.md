# Release v2.3.3-dev629

## Scope

- Adds Flag 1 through Flag 5 country representation to BDC/SDC competitors, isolated Test competitors, WDC identities and judges.
- Keeps Flag 1 in the existing `country` field for backward compatibility and stores the ordered set in `countries_json`.
- Adds administrator Flag 1–5 controls and Profile Integration API `countries` arrays.
- Renders multi-country competitors and judges on shared Test/Live Jack & Jill projection and Dance Cup projection.
- Realigns the Call Judges One by One card inside the existing projector safe area.

## Migration

- Adds new migration `20260904_0300_multi_country_flags`.
- Existing applied migrations were not modified.
- Existing primary countries are copied into the ordered country set.

## Parity Gate

### Candidate/static validation

- Test dashboard path: shared `bdc_test_competitors` schema and shared projection feed checked.
- Live dashboard path: shared `bdc_competitors` schema, profile form and shared projection feed checked.
- Judge path: Judge Database editor, directory storage and one-by-one/all-judge projection checked.
- Dance Cup path: WDC editor, projection feed and projector renderer checked.
- Profile API: competitor, judge and WDC ordered country payloads checked.
- Executable JavaScript and static regression suite: passed.
- Existing migration immutability: passed.
- PHP syntax lint: not run because PHP CLI is unavailable in this workspace.

### Staging/runtime validation

- Not runtime-tested. Production promotion remains blocked until this exact commit passes migration, form save/reopen, Test/Live projection and Dance Cup projection checks on Staging.

## Deployment

- Source candidate only. No Staging or Production deployment performed by Codex.
