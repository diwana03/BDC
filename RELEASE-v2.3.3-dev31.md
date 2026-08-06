# BDC v2.3.3-dev31

## Scoring Dashboard response hotfix

- Removed the forced `Content-Encoding: identity` response header from the Scoring Dashboard.
- Allows Bluehost to advertise and deliver its actual compression encoding so browsers decode the HTML instead of displaying compressed bytes as text.
- Keeps the UTF-8 content type and no-transform/no-store cache safeguards.

No database migration is required.
