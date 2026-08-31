# BDC Profile Integration API v1

This server-to-server API stages bulk Jack & Jill competitor and judge profile updates for administrator review. It never updates points, results, scoring rounds, placements or leaderboards.

## Authentication

Set a random secret of at least 32 characters as `BDC_PROFILE_INTEGRATION_SECRET`. The secret remains valid until it is rotated. Every request is signed and expires after five minutes.

A Super Admin can create or rotate the credential from **Admin → Integration Review**. The portal stores file-managed credentials beside the configured database password file, outside the public application directory, and downloads the new credential once. If `BDC_PROFILE_INTEGRATION_SECRET` is supplied by the server environment, the portal reports it as externally managed and does not replace it.

Required headers:

- `Content-Type: application/json`
- `X-BDC-Timestamp: <current Unix timestamp>`
- `X-BDC-Signature: <hex HMAC-SHA256>`

The signature input is `v1.<timestamp>.<exact raw request body>`. Configure `BDC_PROFILE_INTEGRATION_SCOPES` as `competitors:submit`, `judges:submit`, or both separated by a comma.

## Submit a batch

`POST /portal/api/profile-sync/v1/`

One request accepts up to 50 updates. A larger logical batch can be sent in several requests using the same stable `batch_key`. Every item needs a source-specific `source_key`; repeating the same source system, entity type and source key returns `duplicate`.

```json
{
  "batch_key": "sbta-2026-forms-20260831",
  "source_system": "sbta_google_forms",
  "items": [
    {
      "entity_type": "competitor",
      "source_key": "amateur:sheet-row-75",
      "payload": {
        "form_kind": "amateur",
        "full_name": "Example Dancer",
        "role": "Follow",
        "styles": ["bachata", "salsa"],
        "country": "Singapore",
        "email": "dancer@example.com",
        "photo_mime": "image/jpeg",
        "photo_name": "example.jpg",
        "photo_base64": "<base64 bytes>"
      }
    },
    {
      "entity_type": "judge",
      "source_key": "judge-form:104",
      "payload": {
        "judge_code": "JDG-000104",
        "full_name": "Example Judge",
        "dance_styles": ["bachata", "salsa"],
        "judge_role": "both",
        "qualified_rounds": ["heats", "semifinal", "final"]
      }
    }
  ]
}
```

Photos may be JPG, PNG or WebP and at most 15 MB after base64 decoding. The API validates and stores the submitted bytes without cropping, resizing, rotating, re-encoding or otherwise adjusting the image. A client may compress before submission when desired.

Competitor `form_kind` must be `amateur` or `open`. `styles` may contain Bachata, Salsa or both. Amateur approval adds the relevant Rising registration; Open approval adds the relevant Open registration. It does not change earned permanent division progression.

## Batch status

`GET /portal/api/profile-sync/v1/status.php?batch_key=<batch-key>`

Sign the GET request with `v1.<timestamp>.status:<batch-key>.` so the query target is covered by the signature. The response reports counts by entity, review status and identity-match status without returning private profile payloads.

## Review and approval

Open `/portal/admin/integration-review/`. Competitor and Judge tabs support filters, select all shown, bulk approval and bulk rejection. Clear matches and new profiles can be approved together. Ambiguous or invalid identities must be resolved before approval. Each selected update uses its own transaction, so one failure does not block the remaining selections.

Staged photos stay under the protected `storage` directory and are served only through an authenticated preview. Approved photos are copied byte-for-byte to the appropriate profile upload folder. Rejected items never modify live profiles.

## Legacy Google Form compatibility

`/api/form-sync/` continues accepting its existing signed single-row payloads and `BDC_GOOGLE_FORM_SYNC_SECRET`. It now places each row in the same pending review queue instead of changing a live competitor immediately.

## Explicit exclusions

The integration service does not write to point transactions, participant results, scoring tables, publication approvals, placements, event registrations, payments, user accounts or access permissions.
