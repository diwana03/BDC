# BDC v2.3.3-dev326

## Premium global navigation

- Replaces inconsistent narrow Bootstrap top bars with one shared BDC premium navigation treatment.
- Uses the official BDC logo on a larger white tile with a clear product identity and context label.
- Balances navigation actions at the right edge without leaving the theme selector floating over page content.
- Integrates Light, Dark and System controls into supported navigation bars while retaining Light as the first-time default.
- Applies responsive wrapping and touch-friendly navigation controls for tablets and phones.
- Uses the existing global branding layer so Admin, Test, Live, judge, Dance Cup and scoring pages cannot visually drift.

## Validation

- Candidate/static: shared branding and theme JavaScript syntax passed.
- Candidate/static: version JSON, premium-navigation markers, global branding cache version and whitespace checks passed.
- Candidate/static: every existing Bootstrap administration navbar remains covered by the central branding loader.
- Candidate/static: Light remains the unsaved-preference default and the established Dark theme tokens are unchanged.
- Database migration: not required.
- Staging/runtime: pending user deployment of this exact `develop` commit.
- Production: untouched; promotion remains user-controlled.

## Parity Gate

- Testing Score Dashboard, selector and automatic wrapper: shared navigation/theme loaders checked.
- Live Scoring Dashboard, mode selector and automatic workflow: shared navigation/theme loaders checked.
- Live and Test judge screens: branding and theme assets checked; scoring interaction remains unchanged.
- Dance Cup dashboard/category and Admin dashboard: shared branding/theme entry points checked.
- Projection workspace and projection control: shared branding/theme entry points checked.
- Audience projector: centrally branded but its presentation layout and scoring data are unchanged.
