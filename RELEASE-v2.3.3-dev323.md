# BDC v2.3.3-dev323

## Shared Light and Dark themes

- Adds Light, Dark and System appearance controls to the BDC Admin dashboard and operational scoring screens.
- Persists the preference in the browser and synchronises it across open tabs and embedded projection controls.
- Applies the shared theme to Jack and Jill Live/Test scoring, Live/Test judge screens, Dance Cup and projection controls.
- Keeps the audience-facing projector independent so an operator or judge theme change cannot alter the public presentation.
- Uses local system fonts and BDC CSS variables; no external font, colour or theme dependency was added.
- Removes the redundant full-width Production/version bar. Version information remains in the dashboard sidebar and footer.

## Dance Cup directory workflow

- Adds live Competitor Database suggestions by name or BDC ID.
- Adds live Judge Database suggestions by name or Judge ID.
- Links selected suggestions to their real directory IDs while preserving manual entry for new teams or names.
- Blocks the same linked competitor or judge from being added to the category twice.
- Adds keyboard-accessible suggestion lists and clearer premium competitor/judge setup cards.
- Leaves existing Dance Cup entries, judges, marks, results and checkpoints unchanged.

## Judge Review Later

- Replaces name-first Review Later shortcuts with large bib-first shortcuts.
- Keeps the competitor name as smaller supporting text.
- Applies the same behaviour to Live and isolated Test judge scoring.

## Validation

- Candidate/static: modified PHP source markers and affected-surface wiring passed; this workspace does not provide a PHP CLI parser.
- Candidate/static: theme, Dance Cup directory and judge helper JavaScript syntax passed.
- Candidate/static: CSS braces and required dark/light tokens passed.
- Candidate/static: `VERSION.json` and whitespace validation passed.
- Database migration: not required; existing nullable Dance Cup directory ID columns are used.
- Staging/runtime: pending user deployment of this exact `develop` commit.
- Production: untouched; promotion remains user-controlled.

## Parity Gate

- Test dashboard: `admin/scoring-tests/index.php`, `admin/scoring-tests/select-mode.php`, `admin/scoring-tests/automatic-screen.php` checked.
- Live dashboard: `admin/scoring/index.php` and shared scoring renderer checked.
- Test and Live judges: `test-judge-scoring/index.php`, `test-judge-scoring/final.php`, `judge-scoring/index.php` checked.
- Dance Cup: shared Test/Live `admin/dance-cup/index.php`, `admin/dance-cup/category.php` and directory endpoint checked.
- Projector controls: shared Test/Live `admin/live-screen/projection-workspace.php` and `admin/live-screen/control.php` checked.
- Audience projector: deliberately unchanged and does not load the operator theme control.
