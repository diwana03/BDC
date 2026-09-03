# BDC v2.3.3-dev586

## Emergency Staging recovery

- Restores valid multiline Apache `FilesMatch` containers in the root `.htaccess`.
- Removes the configuration syntax that caused Apache to return HTTP 500 before the Staging Release Manager could execute.
- Preserves the existing physical-directory bypass, so `admin/system-release/` is never routed through the portal front controller.
- Adds a regression test protecting Apache container formatting and the Release Manager routing boundary.

## Validation

- The complete PHP 8.1 lint and JavaScript regression workflow must pass before merge.
- Deploy this corrective release to Staging and verify the Release Manager before testing MCP.
- No database migration and no data mutation.
