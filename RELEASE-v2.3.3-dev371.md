# BDC 2.3.3-dev371 — Reliable Native Emoji Projection Effects

## Runtime finding

- Production was confirmed on `2.3.3-dev370` commit `953a7dd3b796383c605d14880dc5e0c9fd341906`.
- The command, resolved projector session and projector state feed were already connected correctly.
- The remaining shared failure point was canvas emoji drawing with an Arial font, which can produce no visible color emoji on Firefox, macOS and some projector browser environments.

## Fix

- Replaces canvas text drawing for Hearts, Balloons, Smiling Hearts and Korean Finger Hearts with native browser emoji elements.
- Uses explicit Apple Color Emoji, Segoe UI Emoji and Noto Color Emoji font fallbacks.
- Uses compositor-friendly CSS transforms instead of a JavaScript animation frame loop.
- Clears every generated element automatically when an effect ends or another effect starts.

## Performance

- Caps each effect at 24–48 visible elements depending on screen width.
- Runs for nine seconds and cleans itself up.
- Uses one document fragment insertion and GPU-friendly translate transforms.
- Keeps the underlying live projection interactive and avoids continuous canvas redraws.

## Scope

- Applies to all four manual effect buttons on the Emcee page and Test/Live J&J Projection Control.
- Keeps Random Match countdown-only behaviour.
- Does not change Automatic scoring, judge scoring, score calculation or result publication.

## Validation

- Four-effect command-to-projector regression test.
- Native emoji renderer, font fallback, performance cap and cleanup checks.
- Projector JavaScript syntax compilation.
- Existing Emcee countdown, dashboard parity, manual judge-order and live tie tests.
- `git diff --check`.

Deploy this exact `develop` commit to Staging and visibly test each of the four buttons on the projector before Production promotion.
