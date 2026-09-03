# v2.3.3-dev579

## WDC photo replacement API

- Adds `operation: photo_replace` for `wdc_identity` items submitted through the signed profile integration API.
- Requires an exact active `wdc_id` and a valid untouched JPG, PNG or WebP image.
- Stages the original bytes without cropping, resizing or re-encoding.
- Keeps the existing Super Admin WDC review and approval workflow.
- Shows the exact WDC ID in the review card when the payload is photo-only.

## Isolation and safety

- Approval updates only `bdc_wdc_identities.photo_url` for the matched WDC identity.
- The locked identity code is rechecked during approval before publishing the file.
- Photo-only payloads reject name, country, category, registration or other profile fields.
- No BDC profile, SDC profile, WDC registration, scoring, result, point or leaderboard data is changed.
- Existing full WDC registration submissions remain backward compatible.
- No database migration is required.

## Validation

- PASS: all 172 JavaScript regression tests.
- PASS: focused WDC photo-only API isolation test.
- PASS: clean diff whitespace check.
- NOT RUNTIME-TESTED: local PHP/API/database execution because this workspace container has no PHP runtime.
- NOT RUNTIME-TESTED: Staging submission, Super Admin approval and live WDC photo verification until this exact candidate is pushed and deployed to Staging.

## Deployment status

- Built on `develop` after merging the validated `v2.3.3-dev578` line.
- Not pushed.
- Production promotion remains blocked until the exact commit passes Staging runtime validation.
