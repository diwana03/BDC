# Release 2.3.3-dev602

## Separate projector flight-round controls

- Replaces the single generic Flight Call control with one button for every saved flight.
- Displays Flight Round 1, Flight Round 2, Flight Round 3, and later rounds automatically.
- Sends the selected flight number directly to the shared projector session.
- Highlights only the flight round currently shown on the projector.
- Preserves existing scoring assignments, active scoring round, bib order, and Test/Live isolation.

## Verification

- Controls are generated from the existing saved flight summary.
- The selected button updates both the projector screen type and page/flight number.
- No flight assignment or score data is written by the projector control.
