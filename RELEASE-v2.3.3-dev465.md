# BDC 2.3.3-dev465

## Dance Cup participant status repair

- Adds a dedicated status to each Dance Cup registration category.
- Existing approved profile requests are backfilled as approved categories.
- Existing rejected profile requests remain submitted categories instead of being falsely shown as rejected.
- Future Dance Cup profile approval marks its attached categories approved.
- The participant dashboard filters and displays category registration status independently from profile and identity review.

## Safety

- No competitor, category, score, result or profile request is deleted.
- Existing rejected profile-review decisions are preserved in Profile Requests.
- Test and Live use the same additive migration and dashboard logic.
