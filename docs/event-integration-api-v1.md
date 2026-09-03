# BDC Event Setup Integration API v1

This API stages complete Jack & Jill and Dance Cup event setup packages for Super Admin bulk review. It also stages competitor additions to an existing Jack & Jill draft round. It does not write scoring data when a package is submitted. Approval applies the requested package atomically.

## Authorization and signing

The API reuses the protected BDC integration credential. Its event permissions are controlled separately by `BDC_EVENT_INTEGRATION_SCOPES=events:read,events:submit`, so an existing profile-scope configuration does not need to change. The credential remains valid until it is rotated; each signed request timestamp is accepted for five minutes.

For `POST /api/event-sync/v1/`, sign:

`v1.{unix_timestamp}.events:submit.{exact_raw_json}`

For status, sign an empty body with context `events:status:{batch_key}`. For directory search, sign an empty body with context `events:directory:{type}:{data_mode}:{exact_query}`. Send the lowercase SHA-256 HMAC as `X-BDC-Signature` and the Unix timestamp as `X-BDC-Timestamp`.

## Jack & Jill package

```json
{
  "batch_key": "sbta-2026-jj-setup-01",
  "source_system": "chatgpt_bdc",
  "items": [{
    "source_key": "sbta-2026-jj",
    "event_system": "jack_jill",
    "data_mode": "live",
    "payload": {
      "event": {"name": "SBTA 2026 Jack & Jill", "event_date": "2026-11-06", "venue": "Main Ballroom", "country": "Singapore"},
      "rounds": [{
        "round_key": "bachata-open-heats",
        "dance_style": "bachata",
        "division": "bachata_open",
        "round_type": "heats",
        "scoring_mode": "automated",
        "scheduled_at": "2026-11-06 14:00:00",
        "yes_count": 10,
        "callback_count": 10,
        "competitors": [
          {"bdc_id": "BDC-000101", "role": "leader", "bib": 101},
          {"bdc_id": "BDC-000202", "role": "follower", "bib": 201}
        ],
        "judges": [
          {"judge_code": "JDG-000001", "scope": "all", "chief": true},
          {"judge_code": "JDG-000002", "scope": "all", "chief": false},
          {"judge_code": "JDG-000003", "scope": "all", "chief": false}
        ]
      }]
    }
  }]
}
```

Initial Jack & Jill rounds can be `heats` or `final`. Semifinals and later finals are created through the normal callback/progression workflow. Each leader and follower panel must have at least three applicable judges and exactly one Chief Judge.

## Dance Cup package

Use `event_system: "dance_cup"`. The event includes `scoring_mode: "manual"` or `"automatic"`; each item in `categories` includes a unique `category_key`, name, entry type, dance style, level, gender eligibility, performance type, round name, criteria, competitors, and judges. Competitors use `bdc_id`; judges use `judge_code`.

## Add competitors to an existing Jack & Jill draft

Set `operation` to `add_competitors`, identify the existing event and round, and provide the exact council IDs, roles, and bibs to add:

```json
{
  "batch_key": "sbta-2026-test-roster-01",
  "source_system": "chatgpt_bdc",
  "items": [{
    "source_key": "salsa-open-test-roster",
    "event_system": "jack_jill",
    "data_mode": "test",
    "operation": "add_competitors",
    "payload": {
      "target_event_id": 123,
      "target_round_id": 456,
      "competitors": [
        {"council_id": "SDC-000101", "role": "leader", "bib": 101},
        {"council_id": "SDC-000202", "role": "follower", "bib": 201}
      ]
    }
  }]
}
```

The server reads dance style and division from the target round and revalidates them at approval time. The event and round must both remain `draft`. Existing competitor assignments, duplicate council ID and role pairs, cross council IDs, division ineligible profiles, and duplicate bibs within a role are rejected atomically. This operation never changes event settings, judges, scores, results, or publication state.

## Directory and status

- `GET /api/event-sync/v1/directory.php?type=competitor&q=BDC-000101&data_mode=live`
- `GET /api/event-sync/v1/directory.php?type=judge&q=Maria&data_mode=live`
- `GET /api/event-sync/v1/status.php?batch_key=sbta-2026-jj-setup-01`

Directory responses intentionally exclude email, phone, WhatsApp, private notes, and other contact details.

## Review and exclusions

Super Admin reviews packages at `/admin/integration-review/events.php`. “Select all shown” supports one bulk decision across many packages. Each package is atomic and produces only draft configuration.

Out of scope: scores, marks, points, placements, results, publication, leaderboard changes, payments, registrations, automatic progression, deletion, changing existing event or round settings, and sending judge links.
