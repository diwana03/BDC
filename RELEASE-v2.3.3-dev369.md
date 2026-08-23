# BDC 2.3.3-dev369

## Countdown-only Emcee reveal

- Keeps the requested automatic projector countdown: **5, 4, 3, 2, 1**.
- Removes the automatic Champion Impact/blast after the countdown.
- Clears the countdown overlay and reveals the matched couples normally.
- Does not automatically start any celebration effect.
- Removes Fireworks and Confetti from Emcee Random Match.
- Adds four manual premium overlays: **Hearts**, **Balloons**, **Smiling Hearts** and **Korean Finger Hearts**.
- Caps the new overlays at 64 floating elements, renders at approximately 30 FPS, pauses work in hidden tabs and clears automatically after 8.5 seconds to protect projector performance.
- Keeps Clear Effect as a manual control.
- Applies to the shared secure Emcee presenter used by isolated Test and Live Final matching.
- Does not change Manual or Automatic scoring calculations, pairing security or projector screen layouts.

## Parity Gate

### Candidate/static validation

- Shared Test/Live presenter checked: `pairing-presenter/index.php`.
- Shared projector countdown renderer remains in `live-display/index.php`.
- New effect validation is shared through `app/Services/LiveDisplaySessionService.php` and rendering through `live-display/index.php`.
- Updated historical random-reveal and projector-sound assertions for the new countdown-only requirement.
- Added executable regression `tests/emcee-countdown-only-v369.js`.

### Staging/runtime validation

- Start Random Match through an isolated Test Emcee link.
- Confirm the projector holds the pairings during the 5‑4‑3‑2‑1 countdown.
- Confirm couples appear immediately after 1 with no blast or continuing overlay.
- Confirm Hearts, Balloons, Smiling Hearts and Korean Finger Hearts each run only when their individual Emcee buttons are pressed.
- Confirm repeated effect use remains smooth and the matching screen does not freeze.
- Repeat with a Live Final on Staging.
- Production promotion remains blocked until Staging passes.

## Migration

- No database or configuration migration.

## Deployment

- Candidate is for GitHub `develop` and exact-commit Staging deployment only.
- Production remains untouched.
