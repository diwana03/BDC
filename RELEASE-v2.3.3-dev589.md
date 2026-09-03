# BDC v2.3.3-dev589

## MCP Super Admin login correction

- Corrects the OAuth authorization page login link from the nonexistent `/portal/login` route to the real `/portal/admin/` Super Admin login entry.
- Labels the action clearly as BDC Admin login while preserving the existing instruction to return to ChatGPT and reconnect after authentication.
- Adds a regression test preventing the public login path from being reintroduced.

## Validation

- The focused MCP login-route test passes locally.
- PHP 8.1 lint and the complete JavaScript regression workflow must pass before merge.
- No database migration or data mutation.
