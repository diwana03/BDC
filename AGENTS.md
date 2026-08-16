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

### Hard parity gate for R&D

“R&D” means prepare the release and push it to the `develop` release line. It never overrides the Test/Live parity rule.

Before any scoring R&D commit or push, the contributor must verify and report all three surfaces:

1. Testing Score Dashboard and its isolated `bdc_test_*` workflow.
2. Live Scoring Dashboard and its real-data workflow.
3. Live Scoreboard / projector whenever the change affects projected competitors, judges, progress, scores, callbacks, finalists, or results.

The parity check must cover the complete chain: setup, competitor and judge assignment, judge links, draft saving, calculation, submission, completed state, callback/tie handling, next-round creation, print preview, final workflow, labels, validation, and error behavior.

**Release-blocking rule:** if any required surface is missing, different, unverified, or fails, do not commit or push the release. Stop and inform the user immediately with:

- what passed;
- what failed or could not be verified;
- which Test, Live, or projector files remain incomplete;
- whether the previous `develop` release remains unchanged.

Never silently omit a counterpart, defer it to a later release, or describe a partial scoring change as complete.

## Branch and deployment safety

- Develop on `develop`; never make feature changes directly on `main`.
- Never modify or overwrite `config/config.php`.
- Push source changes only after validation. The user deploys `develop` to Staging through Release Manager.
- Never deploy to or modify Production. Production promotion belongs to the user after Staging approval.
- Every release must increment the application version/build and include release notes, validation results, migration status, and deployment status.
- Every scoring release note must include a **Parity Gate** section naming the Test dashboard, Live dashboard, and projector files checked. R&D is blocked when that section cannot truthfully be completed.
