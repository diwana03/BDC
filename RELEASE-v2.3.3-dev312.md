# BDC 2.3.3-dev312 · Build 3018

## Premium scoring-board palette

- Replaces bright utility colours with a restrained premium palette.
- Uses champagne gold for Registration Desk setup and soft sapphire for Flights.
- Uses deep navy for Leader boards and burgundy for Follower boards.
- Preserves the improved setup order from dev311 without changing workflow or scoring behavior.
- Refreshes the shared premium stylesheet version across Test and Live scoring so browsers do not retain the old colours.

## Parity Gate

- **Testing:** shared premium stylesheet and Test Flight hierarchy updated.
- **Live:** Manual and Automatic boards use the same palette.
- **Projector:** no projection layout, commands, results or effects changed.

## Migration and deployment

- Database migration: none.
- Data impact: none.
- Deployment: source candidate only; no Staging or Production deployment performed.
