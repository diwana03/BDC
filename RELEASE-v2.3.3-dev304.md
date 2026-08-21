# BDC 2.3.3-dev304 · Build 3010

## Optional Flights

- Adds optional Flights to Heats, Semifinal and Final in both Live and Test scoring.
- Supports any Flight size from 1 to 50 and an unlimited resulting number of Flights.
- Orders non-Final Leaders and Followers independently by bib number.
- Orders confirmed Final couples by Leader bib, then Follower bib.
- Leaves existing scoring unchanged when Flights are not configured.

## Operations and safety

- Adds a premium **Manage Flights** workspace to scoring dashboards.
- Locks Flight assignment rebuilding as soon as judging starts.
- Allows Scorer, Master Scorer or Super Admin emergency rebuilding only with a reason and typed `REBUILD` confirmation.
- Creates a protected scoring checkpoint before an authorised locked rebuild and records the operation in scoring transactions.
- Adds **Flight Call** to the shared Test and Live projector; the projection Page value selects the Flight number.

## Parity Gate

- **Testing:** shared scoring dashboards expose Flights against isolated Test rounds and the Test projector.
- **Live:** the same Flight service and controls operate against Live rounds.
- **Projector:** the shared Test/Live feed and display render the selected Flight in bib order.
- Static parity checks completed; staging runtime confirmation remains pending in Release Manager.
