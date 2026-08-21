# BDC 2.3.3-dev309 · Build 3015

## Restore complete Flight workflow

- Restores the **Manage Flights** card on the Live Automatic Scoring setup screen.
- Restores the same optional Flight workflow on Live Manual Scoring and isolated Test Scoring.
- Restores `admin/scoring/flights.php` and the shared `ScoringFlightService` that were omitted from the earlier GitHub publication.
- Supports unlimited bib-ordered Flights with a configurable 1–50 dancers per role.
- Keeps Leaders and Followers independently ordered by bib in non-Final rounds.
- Orders confirmed Final couples by Leader bib and then Follower bib.
- Restores active-Flight selection and Flight Call on the shared Test/Live projector control.
- Keeps rounds without Flights on the normal complete competitor list.
- Locks Flight rebuilding after scoring starts, with the existing authorised safety-checkpoint override.

## Parity Gate

- **Testing:** verified Test Scoring links to `flights.php` with `data_mode=test`, uses `bdc_test_*` Flight tables, and exposes Test Flight Call through the shared projector.
- **Live:** verified Manual and Automatic Scoring link to the same shared Flight manager with real-data mode.
- **Projector:** verified shared Test/Live feeds filter to the active Flight only when Flight assignments exist; normal rounds remain unchanged.
- Candidate static validation completed; Staging runtime confirmation remains pending in Release Manager.

## Migration and deployment

- Database migration: Flight tables are created by the shared service for the selected Test or Live data mode.
- Existing scoring data: unchanged.
- Deployment: source candidate only; no Staging or Production deployment performed.
