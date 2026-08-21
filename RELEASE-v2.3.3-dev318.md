# BDC v2.3.3-dev318

Build 3024 · Development release · 22 August 2026

## Judge display order

- Adds Up and Down ordering controls to Jack & Jill judge setup for Test, Live, Heats, Semifinals and Finals.
- Pins the selected Chief Judge as Judge 1 when the panel is saved.
- Preserves the administrator-selected order for every other judge.
- Adds an independent Dance Cup judge-order panel using the separate Dance Cup tables.
- Uses the saved order in scoring matrices, result reports, printed judge sheets, judge progress and Live Projection.
- Labels the projected Chief Judge and each judge's Leader, Follower or All scope.

## Dance Cup scoring workspace

- Replaces the Dance Cup category placeholder with competitor entry, judge setup and criterion marks.
- Adds score-draft saving, sum calculation, tied placement handling, checkpoint creation, results, printing and final submission locking.
- Adds isolated Test and Live Dance Cup entries, judges, marks, results and checkpoint tables.

## Parity Gate

### Candidate/static validation

- Test dashboard: `admin/scoring-tests/index.php`, Test scoring reports and isolated `bdc_test_*` judge ordering checked.
- Live dashboard: shared judge assignment service, Live scoring setup, automatic setup and result report ordering checked.
- Projector: shared `live-display/feed.php` judge query and Chief/scope labels checked for Test and Live modes.
- Dance Cup: separate Test and Live table selection, judge-order endpoint and scoring workspace checked.
- `config/config.php` and Production were not changed.

### Staging/runtime validation

- Pending deployment of this exact `develop` candidate by the user.
- Production promotion remains blocked until Test, Live and projector browser workflows pass on Staging.
