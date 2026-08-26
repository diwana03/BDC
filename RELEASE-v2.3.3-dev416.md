# BDC v2.3.3-dev416

Build: 3122  
Date: 2026-08-26

## Exact migration checksum compatibility

- Confirms migration 0500 itself is unchanged.
- Identifies the remaining mismatch as dependency-aware hashing of SpecialCategoryRecoveryService, which legitimately changed in the next release.
- Changes migration 0500 to stable file-only verification for future releases.
- Accepts only two exact checksums: the dependency-aware checksum recorded by dev413 and the immutable file-only checksum.
- Keeps every unrelated migration checksum mismatch fail-closed.

## Validation

- Reconstructed dev413 dependency-aware checksum: 05879cf08f3131f0a33c0ec38ada73b5e8a08481602ec4c662fb8e887c6dab31.
- Immutable 0500 file-only checksum: 064f1c3a9332383b301663ad43000a088af5e1f45d2bc364c8a572c69902dfd8.
- Narrow compatibility regression: passed.
- Verified 28-profile and separate-category regressions: passed.
- Production runtime: not yet passed; deploy this exact candidate and verify migration 0600 plus application health.

## Deployment

- Candidate published to develop only.
- No Production data was changed by the failed dev415 attempt because migration execution stopped before 0600.
