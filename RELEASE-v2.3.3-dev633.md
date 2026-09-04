# Release v2.3.3-dev633

## Projector fullscreen

- Adds **Enter Full Screen** directly to the Jack & Jill live-display browser.
- Adds the same projector-side control to the Dance Cup projector.
- Hides the control while fullscreen is active and restores it after fullscreen exits.
- Shows an F11 fallback when the browser does not expose the Fullscreen API.
- Keeps projector commands, scoring, tokens and data unchanged.

## Competitor pages

- Retains the maximum of 15 Leaders and 15 Followers per projector slide.
- Keeps the true role totals visible, including rounds with 15 Leaders and 19 Followers.
- Additional Followers continue on the next projector page.

Browser security requires the fullscreen action to be clicked once in the projector browser itself; a phone or separate control browser cannot force another browser window into fullscreen.
