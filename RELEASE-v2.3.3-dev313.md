# BDC 2.3.3-dev313 · Build 3019

## Unique judge selection

- Removes a Judge Database profile from the remaining search suggestions as soon as it is selected.
- Restores that profile to the search list if the selection is cleared or changed.
- Shows an immediate inline error and disables **Submit Judges** when the same judge is entered twice.
- Rejects duplicate Judge IDs at the backend even if browser validation is bypassed.
- Normalizes case and repeated whitespace so variants such as `Joel`, ` joel ` and `JOEL` are treated as the same judge.
- Applies the duplicate-name safeguard to isolated Test scoring as well as Live scoring.

## Parity Gate

- **Testing:** duplicate normalized judge names are blocked.
- **Live:** duplicate Judge IDs and names are blocked in both UI and backend.
- **Projector:** no projection or score-calculation behavior changed.

## Migration and deployment

- Database migration: none.
- Existing judge records: preserved.
- Production deployment: not performed.
