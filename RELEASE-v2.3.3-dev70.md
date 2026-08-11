# BDC v2.3.3-dev70

## Scoring Tests routing fix

- Every normal request to the Scoring Tests Dashboard now opens the Manual/Automatic sandbox.
- Stale POST submissions from the legacy Manual-only tester are redirected to the sandbox instead of running the old competitor-copy code.
- The legacy test dashboard remains available only through the explicit `?legacy=1` escape hatch for validation.
- This prevents the `original_photo_url` legacy competitor-copy error from occurring on the normal Scoring Tests workflow.
- Production scoring, points, progression, publications and Release Manager remain unchanged.
