# BDC Development Rules

These instructions are mandatory for every contributor and coding agent working in this repository.

## Workspace-first rule

- Always inspect the current BDC workspace, repository instructions, existing implementation, version history, and working-tree state before researching, designing, changing, testing, committing, or publishing anything.
- The workspace is the source of truth. Never rely only on conversation memory, screenshots, assumptions, or a newly invented replacement when the existing BDC implementation can be inspected.
- Preserve existing workflows and user data unless the requested change explicitly requires a migration. Never recreate or overwrite established functionality without first tracing how it currently works across Testing, Live, and projector surfaces.
- Before editing, identify all affected counterpart files and shared services so a change does not create dashboard drift or remove an existing feature.

## Meaning of R&D

When the user says **“R&D”**, it means:

1. Inspect the workspace first.
2. Research and analyse the request and the existing implementation.
3. Implement the complete solution in the workspace; do not stop at recommendations, a plan, prose, or a visual mockup.
4. Validate all affected Testing, Live, judge, admin, and projector surfaces as applicable.
5. Update version and release notes, commit the validated work, and publish it to the GitHub `develop` release line unless the user explicitly says not to publish.

R&D must never be reported as complete when only analysis, design, or part of the required workflow exists.

## Test-first, one-release scoring rule

For every feature, correction, workflow, interface, API, or calculation that affects scoring:

1. Implement it on the **Testing Scoreboard first**, using isolated `bdc_test_*` data.
2. Validate the Testing implementation before changing the Live Scoring Dashboard.
3. Implement the same validated behaviour on the **Live Scoring Dashboard**. Prefer shared services and shared UI components so Testing and Live cannot drift.
4. Verify Testing/Live parity, including controls, labels, validation, calculations, errors, and mobile behaviour.
5. Commit and push the Testing and Live implementations **together in one release**. A scoring release must never contain only the Testing implementation or only the Live implementation.

Testing-first describes the internal coding order. It does not permit a Test-only push. Both dashboards must be complete before the release is pushed.

### Hard parity gate for R&D

“R&D” includes preparing the implemented release and publishing it to the `develop` release line. It never overrides the workspace-first or Test/Live parity rules.

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


## Mandatory new-feature sanity gate

This is a **hard release-blocking rule for every new feature and every modification to an existing feature**, including UI-only, JavaScript, PHP, API, scoring, judge, Emcee, projector, report, mobile, save, refresh, lock, and navigation changes.

A feature must not be described as fixed, complete, validated, ready, or published until all applicable checks below pass:

1. **Trace the real runtime path before editing.** Identify the actual rendered page, conditional branch, form action, endpoint, shared service, JavaScript/CSS asset load, database write/read path, and downstream projector/report consumers. Finding matching text somewhere in a source file is not proof that the active runtime path uses it.
2. **Record the pre-change baseline.** Confirm the affected workflow currently works on Testing and Live before adding the feature, or explicitly record the existing failure being corrected.
3. **Test the new feature itself.** Cover normal use, refresh/reload, repeated clicks, empty/incomplete state, validation errors, duplicate input, save/reopen, locked/completed state, and mobile layout whenever applicable.
4. **Run regression checks on connected workflows.** At minimum verify applicable Heats, Semifinal, Final, Manual, Automatic, judge selection and links, Emcee matching, projection, score submission, reports/PDF, scroll restoration, round progression, backups, and permissions.
5. **Verify Testing and Live together.** Test both real entry points and their real runtime render branches. Shared code does not remove the requirement to verify both consumers.
6. **Verify projector parity whenever displayed data or commands can change.** Confirm the open audience display updates correctly, including refresh/polling, holding-screen safety, reveal state, and effects where applicable.
7. **Use executable regression checks.** Add or update a focused automated/static test that asserts the active integration point, not merely the existence of a file, function, string, or early unused HTML branch.
8. **Inspect the final diff and published commit.** Confirm no unrelated feature disappeared, no duplicate control or asset was introduced, version/build and release notes are correct, and the exact files fetched from the resulting commit contain the intended Test, Live, and projector integration.
9. **Report truthfully.** The completion report must list Pass, Fail, and Not Runtime-Tested items. “Not Runtime-Tested” is not a pass and blocks Production approval.

**No-rush rule:** do not bundle another feature while a reported regression remains unresolved. First reproduce and isolate the regression, fix the smallest responsible layer, rerun the affected feature sanity gate, and only then continue.

**Release blocker:** if any applicable sanity check fails, is skipped, or cannot be verified, stop. Do not push the candidate unless the user explicitly authorizes an unverified diagnostic candidate, and never label that candidate complete. Production remains blocked until the exact Staging commit passes runtime sanity checks.


## Branch and deployment safety

- Develop on `develop`; never make feature changes directly on `main`.
- Never modify or overwrite `config/config.php`.
- Push source changes only after validation. The user deploys `develop` to Staging through Release Manager.
- Never deploy to or modify Production. Production promotion belongs to the user after Staging approval.
- Every release must increment the application version/build and include release notes, validation results, migration status, and deployment status.
- Every scoring release note must include a **Parity Gate** section naming the Test dashboard, Live dashboard, and projector files checked. It must distinguish candidate/static validation from Staging/runtime validation. Production promotion is blocked until both can truthfully be completed.
