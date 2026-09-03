# BDC v2.3.3-dev584

## MCP connector

- Adds a Streamable HTTP MCP endpoint for authenticated ChatGPT access.
- Adds OAuth 2.1 discovery, dynamic client registration, S256 PKCE authorization, one-hour access tokens and rotating 30-day refresh tokens.
- Restricts authorization to active BDC Super Admin accounts and stores only token hashes.
- Adds tools to find draft Jack & Jill rounds, list division-eligible competitors, stage approval-gated roster additions and inspect batch status.
- Keeps the protected profile integration credential server-side and preserves the existing Integration Review approval boundary.

## Validation

- Pass: focused MCP connector static regression test.
- Pass: existing event-integration static regression tests.
- Pending: GitHub PHP 8.1 syntax workflow for the candidate commit.
- Not runtime-tested: OAuth connection and MCP tool execution on Staging. Production promotion remains blocked until the exact candidate is deployed and verified on Staging.

## Migration

Additive OAuth client, authorization-code and token tables. No existing event, round, roster, score, result, points or publication rows are changed.
