# BDC v2.3.3-dev682 — MCP competitor photo staging

Build 3389

Adds the `stage_competitor_photo_update` MCP tool for exact BDC or SDC identities. The tool accepts a validated JPG, PNG or WebP, creates an idempotent pending profile package, and cannot change names, countries, roles, divisions or any other profile field. A Super Admin must approve the package in Integration Review before the photo is published.

No database migration is required.
