# BDC v2.3.3-dev345 — Embedded Judge Controls

## Corrections

- Stops the global branding enhancer from floating a second BDC logo over embedded operational panels that already belong to a branded parent screen.
- Keeps official BDC branding on standalone Admin, judge, print and projection pages.
- Replaces iframe-fragile Clipboard API-only behavior with a shared copy helper.
- Tries the secure Clipboard API first, falls back to browser copy selection, and leaves the URL selected for manual copying if the browser blocks both methods.
- Shows temporary `Copied` confirmation on successful copy.
- Covers the Live judge-control cards and the isolated Test judge-link controls through the same central loader.

## Safety

- Presentation and link-copy correction only.
- No judge token, scoring, submission, calculation, database or projector behavior changed.
- No migration or configuration change required.

## Parity Gate

- **Testing Score Dashboard:** Test automatic judge-link Copy control uses the shared fallback helper.
- **Live Scoring Dashboard:** embedded Live Judge Browser Scoring panel uses the same helper and no longer receives a floating duplicate logo.
- **Projector:** unaffected; no projection rendering or scoring payload changed.
- **Candidate/static:** JavaScript syntax, JSON parsing, branding-loader references, iframe guard markers and whitespace validation completed.
- **Staging/runtime:** pending deployment of the exact `develop` commit and browser checks for Copy Link inside Test and Live embedded panels.
- **Production:** unchanged and blocked pending Staging confirmation.
