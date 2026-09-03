# BDC MCP connector

The BDC Portal exposes a stateless Streamable HTTP MCP endpoint at `/portal/mcp`.

It uses OAuth 2.1 authorization code flow with S256 PKCE and dynamic client registration. Only an active BDC Super Admin can authorize access. Access and refresh tokens are random bearer values; only SHA-256 hashes are stored. The existing profile integration secret is never sent to ChatGPT.

Tools:

- `list_event_rounds` lists exact draft Jack & Jill event and round IDs in Test or Live.
- `list_division_competitors` lists eligible council identities for an exact dance and division.
- `stage_competitor_additions` submits an idempotent proposal to the existing Event Integration Review workflow. It never writes a roster directly.
- `get_staged_batch_status` reads validation and approval status.

Connect ChatGPT to `https://bachatadancecouncil.com/portal/mcp`. Both URL forms are accepted without redirecting MCP POST requests. Sign in to the BDC Portal as Super Admin before starting authorization. After staging additions, review them under Administration > Integration Review > Events.
