# BDC 2.3.3-dev166

## AI Operations

- Adds a Super Admin-only AI Operations dashboard.
- Keeps Testing and Live data explicitly isolated, with Testing selected first.
- Generates executive reports, improvement suggestions, optimization reviews, and monitoring reviews.
- Sends only anonymized aggregate operational counts to OpenAI. Names, contact information, BDC IDs, judge identities, and raw scores are excluded.
- Adds deterministic monitoring that remains available without an AI connection.
- Saves reports with model, environment, administrator, timestamp, and usage metadata.
- Adds CSRF protection, audit logging, one-request-per-minute throttling, and a 25-report daily limit.
- AI remains advisory and cannot change competitors, scores, results, or portal settings.

## Server configuration

Set `OPENAI_API_KEY` in the Bluehost PHP/server environment. Optionally set `OPENAI_MODEL`; the default is `gpt-5-mini`. Never place the API key in GitHub or `config/config.php`.
