# BDC Development Rules

These instructions are mandatory for every contributor and coding agent working in this repository.

## Test-first, one-release scoring rule

For every feature, correction, workflow, interface, API, or calculation that affects scoring:

1. Implement it on the **Testing Scoreboard first**, using isolated `bdc_test_*` data.
2. Validate the Testing implementation before changing the Live Scoring Dashboard.
3. Implement the same validated behaviour on the **Live Scoring Dashboard**. Prefer shared services and shared UI components so Testing and Live cannot drift.
4. Verify Testing/Live parity, including controls, labels, validation, calculations, errors, and mobile behaviour.
5. Commit and push the Testing and Live implementations **together in one release**. A scoring release must never contain only the Testing implementation or only the Live implementation.

Testing-first describes the internal coding order. It does not permit a Test-only push. Both dashboards must be complete before the release is pushed.

## Branch and deployment safety

- Develop on `develop`; never make feature changes directly on `main`.
- Never modify or overwrite `config/config.php`.
- Push source changes only after validation. The user deploys `develop` to Staging through Release Manager.
- Never deploy to or modify Production. Production promotion belongs to the user after Staging approval.
- Every release must increment the application version/build and include release notes, validation results, migration status, and deployment status.

