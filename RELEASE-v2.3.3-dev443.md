# BDC 2.3.3-dev443

## Complete Dance Cup workspace

- Removes the forced Step 2 anchor from the legacy Automatic Dance Cup redirect.
- Opens at the event and category header.
- Keeps contestant assignment, judge assignment, ordering, removal and Chief Judge controls visible before live scoring.
- Retains the separated Jack & Jill and Dance Cup sidebar navigation from dev442.
- Does not change scoring calculations, scores or database data.

## Validation

- Complete JavaScript regression suite passed.
- Dance Cup entrypoint regression confirms the redirect no longer skips the roster.
- `git diff --check` passed.
