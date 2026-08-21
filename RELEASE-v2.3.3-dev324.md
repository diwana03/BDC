# BDC v2.3.3-dev324

## Premium dark theme correction

- Rebuilds Dark mode around layered near-black surface tokens instead of one flat navy canvas.
- Uses brighter primary and secondary foreground colours with stronger field and panel borders.
- Gives instructions, warnings, success states and Review Later their own readable dark semantic surfaces.
- Gives YES, A1, A2, A3, LATER and NO clear inactive and active colour pairs in Dark mode.
- Keeps Light as the default for a browser with no saved BDC appearance preference.
- Preserves explicit Light, Dark or System choices already saved by a user.

## Judge branding

- Removes the redundant `BDC AUTOMATIC SCORING` subtitle beneath the BDC logo.
- Applies the correction to Live criteria, Live scoring, Test criteria, Test Heats/Semifinal and Test Final judge screens.
- Keeps the Test-only marker on isolated Test judge screens.

## Research basis

- Uses separate semantic tokens for backgrounds, surfaces, foregrounds, borders and status states.
- Uses multiple dark surface levels to communicate hierarchy instead of relying on shadows alone.
- Targets WCAG AA contrast of at least 4.5:1 for normal text and 3:1 for large text and essential controls.
- Avoids direct light-to-dark colour inversion and keeps saturated BDC wine and champagne colours as restrained accents.

## Validation

- Candidate/static: shared theme JavaScript syntax passed.
- Candidate/static: CSS structure, required tokens and semantic dark-state selectors passed.
- Candidate/static: calculated core text, muted text and semantic-panel colour pairs meet or exceed 4.5:1.
- Candidate/static: redundant judge subtitles are removed through the shared Live/Test render paths.
- Database migration: not required.
- Staging/runtime: pending deployment of this exact `develop` commit by the user.
- Production: untouched; promotion remains user-controlled.

## Parity Gate

- Test dashboard and automatic workflow theme loading checked.
- Live dashboard and automatic workflow theme loading checked.
- Live/Test judge criteria, Heats, Semifinal and Final render paths checked.
- Dance Cup, shared projection workspace and projection control theme loading checked.
- Audience projector remains deliberately independent of operator appearance settings.
