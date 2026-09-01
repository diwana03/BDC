# BDC 2.3.3-dev552 · build 3258

## Alvin discipline correction

- Removes the testing-only Salsa profile and Salsa special-category assignments from Alvin Foo Dun Zhi (`BDC-000248`).
- Removes the testing-only SDC identity so he no longer appears in the Salsa J&J Competitor dashboard.
- Preserves his BDC identity, Bachata profile, contact details, photo and complete Bachata competition history.
- Refuses to run the cleanup if any official Salsa points or participant results exist, requiring manual review instead of deleting official history.

## Validation

- Full JavaScript/static regression suite: passed.
- `git diff --check`: passed.
- PHP CLI/runtime validation: unavailable in this workspace.
- Run migration `20260901_0400_remove_alvin_test_salsa_profile.php` after deployment.
