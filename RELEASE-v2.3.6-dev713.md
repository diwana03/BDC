# BDC v2.3.6-dev713

## Legacy WDC crop recovery and projector refresh

- Recovers the saved WDC adjusted photo for older Dance Cup roster rows whose direct WDC identity link was not populated, using their preserved shared competitor link.
- Applies that adjusted photo consistently to Contestant Call, All Contestants, Live Scoreboard, and Winner Podium.
- Increases All Contestants portraits to a responsive 96 to 148 pixel range while retaining the projector safe canvas.
- Revisions the projector launch URL so reopening the display loads the new inline presentation layout.
- Does not change marks, scores, placements, judging, BDC identities, or SDC identities.

## Validation

- Focused WDC identity, legacy roster, projector refresh, scale, recovery, parity, and safe-layout static tests: passed locally.
- Public server version check before this repair confirmed dev712 was deployed.
- PHP syntax: not locally runtime-tested because PHP CLI is unavailable in this workspace.
- Deployment: `develop` Test candidate only.
- Production: untouched and blocked pending separate runtime approval.
