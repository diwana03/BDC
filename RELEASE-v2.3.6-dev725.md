# BDC 2.3.6-dev725

## Change

- Repairs the remaining ChatGPT connector reconnection failure after MCP tool discovery succeeds.
- A valid OAuth authorization request is now kept in the BDC server session while the user signs in through the normal Super Admin password and two-factor flow, then resumes automatically at the consent screen.
- The resume destination is reconstructed server-side, expires after 10 minutes and cannot be replaced with a user-controlled return URL.
- Logged-in users without the Super Admin role receive a clear forbidden response instead of being sent through a login loop.
- No event, round, competitor, score, result, approval or permission data is changed by this release.

## Validation

- Root cause: dev724 exposes public MCP initialization, tool discovery and OAuth metadata correctly, but an unauthenticated authorization request stops at a 401 instruction page and loses the request after Super Admin login.
- Candidate/static: focused OAuth login-resume, ChatGPT linking, OAuth, physical discovery, connector, cached withdrawal bridge, roster amendment and event integration checks pass.
- PHP runtime: unavailable in the local workspace; PHP syntax and end-to-end browser linking remain Not Runtime-Tested and block Production promotion until this exact candidate passes on Staging.
- Database migration: none.
- Deployment: source candidate only for `develop`; user deployment to Staging is required. Production is unchanged.
