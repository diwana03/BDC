# Release v2.3.3-dev634

## Projector recovery

- Moves each competitor flag and country onto a dedicated full-width bottom row.
- Prevents country names from breaking in the middle of a word on competitor, flight and judge screens.
- Forces the repaired roster stylesheet to refresh under a new cache key.
- Keeps the current projector visible until the replacement page and repaired stylesheet are ready, eliminating the white/black refresh flash.

## Scoring Round distribution

- Balances Leaders and Followers independently across the same number of configured Scoring Rounds.
- Prevents Round 1 from being filled while Round 2 receives only a small remainder or no dancers from one role.
- Preserves bib order, Test/Live isolation, assignment locking and the configured maximum dancers per role.
- Keeps projector page totals based on the larger role count, so 15 Leaders and 19 Followers produce two competitor pages rather than three.

Existing locked Scoring Round assignments remain protected. An authorised rebuild continues to be required after scoring has started.
