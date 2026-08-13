# BDC 2.3.3-dev171

## Safer projector start

- Selecting an event and round now starts the projector on Holding Screen.
- Previous presentation effects, podium state, page, and projector loop are cleared.
- This behaviour is identical in Testing and Live.

## Persistent effects

- Effects remain active until the administrator selects **Clear Effect**.
- Fireworks continue producing cinematic bursts and launch trails.
- Confetti and Gold Celebration recycle naturally for continuous projection.
- Laser Sweep, Drum Roll, and Champion Impact continue until cleared.

## Ranked podium Drum Roll

- Revealing 5th, 4th, 3rd, 2nd, or 1st automatically starts Drum Roll after the podium screen updates.
- Visual scale and intensity increase with each placement.
- 5th is restrained; 1st receives the largest and brightest Drum Roll.

## Deployment

- No database migration is required.
- This release is committed locally and must not be pushed until explicitly approved.
