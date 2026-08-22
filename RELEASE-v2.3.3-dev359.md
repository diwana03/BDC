# BDC 2.3.3-dev359 — Dance Cup Wording Cleanup

## Outcome

- Replaces `Change Workflow` with `← Scoring Options`.
- Removes repeated and technical workflow wording.
- Uses short labels: `Select Category`, `Events & Categories`, and `Open`.
- Fixes workflow title and description contrast on the premium gradient header.

## Parity Gate

### Candidate/static validation

- Testing and Live use the same shared Dance Cup selector and workflow page.
- Test links preserve `data_mode=test`.
- Manual, Automatic and Projection destinations remain unchanged.
- Light and dark theme header text is explicitly readable.

### Staging/runtime validation

- Pending deployment of this exact `develop` commit by the user.
- Production promotion remains blocked until all three workflows are checked on Staging.

## Deployment

- Candidate target: GitHub `develop`.
- No Production action performed.
