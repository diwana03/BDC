# BDC 2.3.3-dev370 — Emcee Effects and J&J Projection Controls

## Fixed

- Fixes Hearts, Balloons, Smiling Hearts and Korean Finger Hearts when the event uses a shared Festival projector.
- Resolves the active standalone or Festival projector session first, then updates that exact session instead of silently writing by the event's primary projector key.
- Applies the same resolved-session targeting when the Emcee countdown moves from Holding Screen to the Final matching screen.

## Test + Live parity

- Adds all four manual effects to the shared J&J Live Projection dashboard used by isolated Testing and Live data modes.
- Keeps the effects available on the restricted Emcee matching page.
- Leaves Automatic scoring, judge marks, calculation and Final placement logic unchanged.

## Emcee wording

- Uses **Random Matching Method: Secure Fisher–Yates Shuffle**.
- Explains plainly that Leaders remain in bib order while Followers are securely shuffled with an equal chance of matching any Leader.
- Removes PHP implementation language and unrelated Draft/Chief Judge wording from the Emcee note.

## Performance

- Retains the capped 28–64 emoji overlay, approximately 30 FPS rendering, hidden-tab pause and automatic cleanup after 8.5 seconds.
- No fireworks or confetti starts automatically after Random Match; only the 5-4-3-2-1 countdown remains automatic.

## Validation

- `node tests/emcee-effects-session-v370.js`
- `node tests/emcee-countdown-only-v369.js`
- `node tests/emcee-matching-dashboard-v367.js`
- `node tests/manual-final-judges-before-pairing-v368.js`
- Projector JavaScript syntax compilation
- `git diff --check`

Deploy this exact `develop` commit to Staging and validate the four effects from both the Emcee page and J&J Projection Control before any Production promotion.
