# BDC v2.3.3-dev378

## Final Judge Database search

- Connects every Final Judge Selection name field to the existing one-character Judge Database search.
- Applies to Manual and Automatic Finals in both isolated Testing and Live.
- Dynamically added Final judges receive the same search dropdown.
- Selecting a result stores its Judge Directory ID; typing a genuinely new name remains allowed.
- Bumps the shared search asset version so browsers do not keep the old script.
- Does not change judge assignment, Chief Judge, scoring, pairing, calculation, result, or projector logic.

## Parity Gate

- Testing and Live use the same shared search module and endpoint.
- Static regression completed.
- No database migration.
- Staging runtime validation is pending; Production remains untouched.
