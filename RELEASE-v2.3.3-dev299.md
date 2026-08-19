# BDC 2.3.3-dev299 · build 3005

## Custom Holding Screen

- Adds a Holding Screen artwork uploader to each saved festival projection.
- Accepts JPG, PNG and WebP files up to 12 MB with a minimum resolution of 1280 × 720.
- Uses the image as a full-screen cover without adding the default event-name overlay.
- Uploading or removing artwork immediately returns the audience projector to Holding Screen.
- Stores artwork under the persistent uploads directory so deployments do not remove it.

## Multi-event results playlist

- Adds an ordered playlist builder for every Final belonging to the selected festival events.
- Each Final can contribute its Winner Podium, Final Score Matrix, or both.
- Supports 5–60 second slide durations.
- Every playlist slide switches event, Final round and result screen together.
- Starting the playlist requires explicit confirmation that results are ready for public display.
- **Stop & Hold** immediately disables the playlist and returns the projector to Holding Screen.
- Any manual event/round selection also stops the playlist before opening its controls.

## Safety and parity

- Uses the same implementation for Test and Live while keeping their event and round data isolated.
- Rejects playlist rounds that are not Finals or are outside the festival session membership.
- Requires at least two valid slides before a loop can start.
- Preserves the existing single-round Projector Tab Loop independently.
