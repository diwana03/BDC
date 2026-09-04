# Release 2.3.3-dev601

## Balanced split-role projector pagination

- Distributes Leader and Follower competitors evenly across every projector slide.
- Prevents a full first slide followed by tiny remainder slides such as 8 Leaders and 4 Followers.
- Preserves existing bib order, Leader/Follower separation, screen capacity, and Test/Live behavior.
- Applies to split-role competitor, callback, finalist, and flight projection screens.

## Verification

- 38 competitors across 2 slides: 19 / 19.
- 34 competitors across 2 slides: 17 / 17.
- 64 competitors across 3 slides: 22 / 21 / 21.
- Empty and undersized role lists remain safe.
