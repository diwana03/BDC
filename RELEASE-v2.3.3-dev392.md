# BDC v2.3.3-dev392

## Dashboard-approved AI testing access

- Adds a public request page for short-lived System Test access without accepting or bypassing Admin credentials.
- Shows pending requests only to a signed-in Super Admin on the normal BDC Admin Dashboard.
- Requires explicit dashboard approval before the requesting browser can open the runner.
- Binds approval to the requesting browser user-agent, hashes every secret at rest, rate-limits requests and expires pending requests after 15 minutes.
- Approved links expire after 20 minutes and can access only the isolated `bdc_test_*` System Test Runner.
- Does not create an Admin session and cannot access users, backups, settings, real events, Live scoring, publication or points.
- Records the approving Super Admin and audits every approval or denial.

## Validation

- Complete JavaScript regression suite: PASS.
- Focused scoped-access security regression: PASS.
- Diff whitespace and JSON validation: PASS.
- PHP syntax: not locally runtime-tested because PHP is unavailable in this workspace.
- Production runtime: not tested; deploy and approve one request before promotion approval.

## Parity Gate

- Testing Score Dashboard: isolated runner only.
- Live Scoring Dashboard: unchanged and inaccessible to scoped access.
- Projector: unchanged and inaccessible to scoped access.

## Migration and deployment

- Additive table: `bdc_system_test_access_requests`.
- Configuration changes: none; `config/config.php` unchanged.
- Deployment target: `develop`; user controls Production deployment.
