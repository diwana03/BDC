# BDC 2.3.3-dev285 · Build 2991

## Live scoreboard isolation from Test tools

- Detects when the Automatic Test wrapper has opened a Live Automatic scoring surface.
- Immediately hands the browser over to the correct Live Automatic dashboard route.
- Prevents a Live round ID from being queried against `bdc_test_scoring_rounds`.
- Removes the false **Test round not found** and **Automatic Test panel failed** errors from Live scoring.
- Preserves existing Live judge links, submissions, score locks and emergency controls.

## Parity and isolation

- Test Automatic continues using the Test gateway and Test database.
- Live Automatic Heats, Semifinal and Final continue using the Live gateway and Live database.
- Test-only generators remain excluded from Live.

## Deployment

- Database migration: none.
- Push target: `develop`.
- Production deployment: not performed.
