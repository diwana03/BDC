# BDC 2.3.3-dev1

## Included

- Role-specific Straight-to-Final eligibility. Points and real competition history are evaluated only for the selected Leader or Follower record.
- Known Novice competitors below 20 Novice points are blocked from Intermediate entry.
- Registration Desk split into Leader and Follower panels with bib editing, check-in, ready and withdrawn status.
- Instant desk saving and lightweight live polling replace the five-second full-page refresh.
- Search by competitor name, BDC ID or bib, plus role totals and missing-bib counts.
- Manual Live Sync Now and Off/Daily/Weekly Production-to-Staging database refresh controls.
- Staging backup, automatic restore, status recording and preserved Staging administrators/deployment history.
- Multiple hard locks prevent Staging-to-Production database sync.

## Staging configuration

Configure `staging_database_sync.production_readonly_database` in Staging `config/config.php` with a MySQL account granted read-only Production access. Set `enabled` to `true` only after verifying those grants.

For scheduled refresh, run `php bin/staging-db-sync.php --scheduled` hourly from the Staging cron service. The saved Off/Daily/Weekly selection and configured quiet hour determine whether a refresh is due.

No Production deployment or Production schema change is included.
