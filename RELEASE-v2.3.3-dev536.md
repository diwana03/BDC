# BDC v2.3.3-dev536

## Dance Cup Test approval isolation

- Routes Dance Cup approval review, approval queue, detailed results, private comments, rankings and workspace links through the selected Test or Live table map.
- Preserves `data_mode=test` through Manual and Automatic detailed-result links, AJAX-enabled approval links, approval queue navigation, print links and approval form submissions.
- Allows Super Admin to complete an isolated Test approval simulation without writing any record to permanent Live Dance Cup history.
- Adds explicit TEST ONLY labels and confirms that permanent history remains unchanged after Test approval.

## Validation

- Added a focused regression test covering Test and Live table selection, forbidden Live-only Test reads, link propagation and permanent-history protection.
- Updated the existing Super Admin approval review regression for shared Test and Live routing.
- No database migration is required.

## Parity Gate

- Testing Score Dashboard: shared Manual and Automatic routes checked with isolated `bdc_test_dance_cup_*` tables.
- Live Scoring Dashboard: the same routes continue to select `bdc_dance_cup_*` tables and retain permanent publication behaviour.
- Projector: no projector payload, polling, effects or reveal behaviour changed.
- Candidate/static validation is included in this release. Staging/runtime validation of this exact commit is required before Production promotion.

## Deployment status

- Candidate targets `develop` only.
- Production promotion remains blocked until the exact commit passes Staging runtime verification.
