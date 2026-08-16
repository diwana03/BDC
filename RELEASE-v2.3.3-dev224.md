# BDC 2.3.3-dev224

## Hard Test / Live / Projector parity rule

- R&D is blocked unless the Testing Score Dashboard, Live Scoring Dashboard and affected Live Scoreboard projector path are complete and verified together.
- A Test-only or Live-only scoring implementation cannot be committed or pushed as a completed release.
- If a required counterpart fails or cannot be verified, work must stop and the user must be told exactly what passed, what failed and what remains incomplete.
- “R&D” does not override the parity gate.
- Future scoring release notes must explicitly name the Test, Live and projector files checked.

## Parity Gate

- Test rules: `AGENTS.md` and `docs/AI.md`
- Live rules: `AGENTS.md` and `docs/AI.md`
- Projector rules: explicitly included in both mandatory instruction files
- Intentionally Test-only tools: random score generators, test-data reset tools and isolated `bdc_test_*` fixtures

## Deployment

- Documentation and release policy only.
- No database migration.
- Production is not modified.
