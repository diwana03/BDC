# BDC v2.3.3-dev145

Build 2475 · Development release · 13 August 2026

## Automatic Testing matrix

- Separated Leaders and Followers into distinct matrix sections.
- Restarted provisional ranking from 1 for each role.
- Preserved fixed-couple display for Final rounds.

## Readable score printing

- Printed Leaders and Followers separately.
- Limited large reports to 10 judge columns and 20 competitors per page.
- Repeated Bib, competitor, total, result and the relevant judge key on every page.
- Removed **Print Judge Sheets** from Automatic Testing while retaining **Preview / Print Scores**.

## Cancel Final repair

- Fixed Automatic Testing retaining a deleted Final round ID after cancellation.
- The wrapper now trusts the round rendered by the Test dashboard and synchronizes its URL to the restored parent round.

## Scope

- Testing dashboard and Test score report only.
- No Production deployment or configuration changes.
