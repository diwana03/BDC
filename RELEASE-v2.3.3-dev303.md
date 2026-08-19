# BDC 2.3.3-dev303 · build 3009

## Projector controller and sound

- Prevents PHP notices, warnings or incidental output from corrupting projector command JSON.
- Logs suppressed controller output server-side for diagnosis without exposing it to Projection Control.
- Keeps correct JSON error status and messages for failed commands.
- Adds **25%, 50%, 75% and 100%** volume selection beside **Open Projector With Sound**.
- Carries the selected volume safely through the holding-screen launcher into the shared sound engine.
- Preserves the required one-click browser audio activation gate.
- Applies identically to Test and Live projection controls.
