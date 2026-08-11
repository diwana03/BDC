# BDC v2.3.3-dev52

## Release Manager Display

- Restores the Release Manager exactly to the known-good dev51 workflow after an accidental edit during development.
- Adds the current build number beside the Current Staging Release version.
- Build display is read from the installed release data, with VERSION.json as the fallback.
- No deployment workflow, promotion logic, scoring logic, database migration or Production behavior changed.
