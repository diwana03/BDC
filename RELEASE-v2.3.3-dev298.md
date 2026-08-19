# BDC 2.3.3-dev298 · build 3004

## Fast festival projection

- Replaces the repeated Event → Division → Round dropdown workflow in a saved festival session with one-click event tabs.
- Automatically opens the only division when an event has one, then presents its available Heats, Semifinal and Final buttons.
- Clears the operator frame immediately when switching events so the shared audience projection safely remains on Holding Screen.
- Uses the same shared workspace for Test and Live projection.

## Judge-link delivery

- Reads each assigned judge's email and WhatsApp/phone from the central Judge Database.
- **Send Email** submits through the website mail server instead of opening a blank local email draft.
- **Send WhatsApp** opens a pre-addressed message to the saved judge number.
- Missing contact methods are visibly disabled instead of creating an unaddressed message.
- Records mode, event round, judge assignment, recipient, channel, outcome, operator and time for each delivery attempt.
- Keeps Test and Live delivery isolated while sharing the same contact and audit implementation.

## Operational note

Backend email reports that the hosting mail server accepted the message; final inbox delivery remains dependent on the server's mail/DNS configuration. Direct unattended WhatsApp sending still requires a separately configured WhatsApp Business API, so this release opens a pre-addressed message for operator confirmation.
