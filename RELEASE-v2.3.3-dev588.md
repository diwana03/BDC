# BDC v2.3.3-dev588

## Production-proven MCP OAuth discovery repair

- Replaces the nonfunctional Production `.htaccess` discovery dependency with physical protected-resource and authorization-server well-known endpoints.
- Uses the working `/portal/mcp/` transport URL as the canonical OAuth resource and documents the required trailing slash.
- Advertises OAuth scopes in the HTTP challenge and declares exact OAuth security schemes on every MCP tool.
- Preserves Super Admin OAuth authorization and Integration Review approval before any competitor roster change.

## Evidence and validation

- External Production diagnostic proved the prior no-slash MCP URL returned HTTP 301 and both expected well-known URLs returned HTTP 404.
- PHP 8.1 lint and the complete JavaScript regression workflow must pass before merge.
- After deployment, the same external diagnostic must prove both physical discovery documents return JSON before retrying ChatGPT.
- No database migration or data mutation.
