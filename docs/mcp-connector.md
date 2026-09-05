# BDC MCP connector

The BDC Portal exposes a stateless Streamable HTTP MCP endpoint at `/portal/mcp/`.

It uses OAuth 2.1 authorization code flow with S256 PKCE and dynamic client registration. Only an active BDC Super Admin can authorize access. Access and refresh tokens are random bearer values; only SHA-256 hashes are stored. The existing profile integration secret is never sent to ChatGPT.

Tools:

- `list_event_rounds` lists exact draft Jack & Jill event and round IDs in Test or Live.
- `list_division_competitors` lists eligible council identities for an exact dance and division.
- `stage_competitor_additions` submits an idempotent proposal to the existing Event Integration Review workflow. It never writes a roster directly.
- `get_staged_batch_status` reads validation and approval status.
- `diagnose_projection` performs read-only Jack & Jill or Dance Cup projection integrity checks in Test or Live. It reports event and round/category selection, display session state, rosters, bibs, profile links, judges, country/flag/photo completeness, flights, pagination, results and reveal safety. It never returns projector access tokens and never changes projection or scoring data.

Connect ChatGPT to `https://bachatadancecouncil.com/portal/mcp/`. The trailing slash is required by the Production web-server configuration. Sign in to the BDC Portal as Super Admin before starting authorization. After staging additions, review them under Administration > Integration Review > Events.

## Projection diagnostics

Call `diagnose_projection` with `event_system`, `data_mode` and the exact `event_id`. For Jack & Jill, `round_id` is optional; for Dance Cup, `competition_id` is optional. If omitted, the active projection selection is checked, falling back to the first event round/category when no session selection exists.

The response separates `pass`, `warning` and `fail` checks. Warnings identify incomplete presentation metadata such as photos or countries. Failures identify broken runtime files, database reads, invalid event/round relationships, duplicate bibs, page bounds or reveal-lock inconsistencies.

This API validates server-side projection integrity. Pixel alignment, animation smoothness, browser fullscreen behaviour and the physical 10 m × 5.5 m 4K display still require a screenshot or on-screen inspection.
