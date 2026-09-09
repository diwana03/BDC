# BDC 2.3.6-dev724

## Change

- Repairs ChatGPT connector installation by allowing public MCP `initialize`, `ping`, notifications and `tools/list`, so ChatGPT can discover the server and each tool's OAuth policy before an account is linked.
- Protected `tools/call` requests still require an active BDC Super Admin OAuth token with the exact read or stage scope.
- An unauthenticated protected tool call now returns the ChatGPT-compatible `_meta["mcp/www_authenticate"]` Bearer challenge and points to the working protected-resource metadata endpoint.
- No event, round, competitor, score, result, approval or permission data is changed by this release.

## Validation

- Live pre-change baseline: Production dev723 served the MCP endpoint and nested OAuth metadata, and dynamic client registration succeeded, but ChatGPT reported the selected development connector as not installed after its setup attempt.
- Live standards probe: the canonical MCP endpoint, nested protected-resource metadata, authorization-server metadata and DCR endpoint responded over HTTPS.
- Candidate/static: focused ChatGPT linking, OAuth, physical discovery, connector, tool discovery, cached withdrawal bridge, roster amendment and event integration checks passed.
- PHP runtime: not available in the local workspace; PHP syntax and end-to-end OAuth browser linking remain Not Runtime-Tested and block Production promotion until the exact candidate passes on Staging.
- Database migration: none.
- Deployment: source candidate only for `develop`; user deployment to Staging is required. Production is unchanged.
