# BDC v2.3.3-dev219

## Judge Submission Readiness

- Keeps the scoring Submit button grey and disabled while required selections are incomplete or still saving.
- Turns the button green and changes its label to `READY TO SUBMIT` only when every assigned role is complete.
- Requires exactly 5, 10 or 15 YES selections according to the configured tier, plus one A1, A2 and A3.
- For an unusually small role, alternates reduce only when fewer than three dancers remain after all required YES selections.
- Applies equivalent ready-state behaviour to Final rankings: every rank must be present, valid and unique.

## Enforcement

- The server independently validates the same YES and alternate requirements when Submit is received.
- Browser manipulation cannot bypass incomplete scoring requirements.
- Existing marks, judge sessions and submitted scores are preserved.

## Safety

- Changes are limited to the `develop` release line; Production is not modified.
