# BDC 2.3.3-dev29

Build: 2359  
Date: 6 August 2026

## Fix

- Prevents the Scoring Dashboard from displaying compressed response bytes as unreadable text.
- Disables conflicting PHP zlib and Apache gzip handling for this page.
- Forces a normal UTF-8 HTML response and prevents intermediary response transformation.
- Does not change scoring logic, events, results, points, or the database schema.

## Deployment

Push target: `develop` only. Staging and Production are not deployed automatically.
