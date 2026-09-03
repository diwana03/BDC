# BDC v2.3.3-dev587

## ChatGPT MCP connection repair

- Routes both `/portal/mcp` and `/portal/mcp/` directly to the Streamable HTTP endpoint, preventing Apache from redirecting a POST or changing its method.
- Implements OAuth resource-indicator validation across authorization, code exchange and refresh-token requests using the exact canonical MCP resource.
- Preserves the resource through the Super Admin consent form and publishes a complete dynamic-client-registration response.
- Keeps the connector approval-gated: it can stage competitor additions but cannot write event rosters directly.

## Validation

- Static MCP connector, OAuth resource-binding and Apache Release Manager safety checks pass locally.
- The GitHub PHP 8.1 lint and full JavaScript regression workflow must pass before merge to `develop`.
- No database migration and no existing event, competitor, judge, score, result or point data is changed.
- After deployment, verify OAuth connection and an MCP `tools/list` handshake before Production use.
