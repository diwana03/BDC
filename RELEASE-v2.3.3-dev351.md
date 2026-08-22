# BDC 2.3.3-dev351

## Dance Cup Judge Database query repair

- Restores Judge Database suggestions from the first typed character in Dance Cup category setup.
- Uses unique native MySQL prepared-statement bindings for every searchable judge field.
- Prioritises full-name and display-name prefix matches, then contains matches.
- Shows a visible retry message when the directory request fails instead of hiding the dropdown.
- Bumps the Dance Cup directory asset version so deployed browsers load the correction immediately.

## Parity Gate

- Testing Dance Cup dashboard: shared `admin/dance-cup/category.php` path with isolated Test tables checked.
- Live Dance Cup dashboard: the same category and directory endpoint checked.
- Judge scoring and projector: no scoring, calculation, submission, or projection logic changed.
- Candidate/static validation: JavaScript syntax, PHP source markers, JSON version and focused regression checks completed locally.
- Staging/runtime validation: pending deployment of this exact `develop` candidate by the user.
- Production promotion: blocked until Staging confirms first-character Judge Database suggestions and selection.

## Migration and deployment

- Database migration: none.
- Deployment target: GitHub `develop`; user deploys to Staging.
