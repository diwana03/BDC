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

Before any scoring R&D candidate is pushed to `develop`, the contributor must statically verify and report all three surfaces:

1. Testing Score Dashboard and its isolated `bdc_test_*` workflow.
2. Live Scoring Dashboard and its real-data workflow.
3. Live Scoreboard / projector whenever the change affects projected competitors, judges, progress, scores, callbacks, finalists, or results.

The parity check must cover the complete chain: setup, competitor and judge assignment, judge links, draft saving, calculation, submission, completed state, callback/tie handling, next-round creation, print preview, final workflow, labels, validation, and error behavior.

The release gate has two stages:

1. **Candidate gate:** complete the Test, Live, and projector changes together; run all locally available syntax, static, and automated checks; then push the versioned candidate to `develop`.
2. **Runtime gate:** the user deploys that exact `develop` commit to Staging. Verify Staging health and the complete browser workflow before the release can be promoted to Production.

**Release-blocking rule:** if any required static check fails, do not push the candidate. If a runtime check fails or cannot be verified on Staging, do not approve or promote the release. Stop and inform the user immediately with:

- what passed;
- what failed or could not be verified;
- which Test, Live, or projector files remain incomplete;
- whether `develop` or the Staging-tested release remains unchanged.

Never silently omit a counterpart, defer it to a later release, or describe a partial scoring change as complete.

## Branch and deployment safety

- Develop on `develop`; never make feature changes directly on `main`.
- Never modify or overwrite `config/config.php`.
- Push source changes only after validation. The user deploys `develop` to Staging through Release Manager.
- Never deploy to or modify Production. Production promotion belongs to the user after Staging approval.
- Every release must increment the application version/build and include release notes, validation results, migration status, and deployment status.
- Every scoring release note must include a **Parity Gate** section naming the Test dashboard, Live dashboard, and projector files checked. It must distinguish candidate/static validation from Staging/runtime validation. Production promotion is blocked until both can truthfully be completed.
