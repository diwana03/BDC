# BDC v2.3.3-dev216

## Automatic Judge Live Links

- Restores the complete Judge Browser Scoring control panel on the Automatic dashboard.
- Shows secure Copy, Open, WhatsApp and Email Important actions for every judge.
- Shows Regenerate Link when an earlier one-way token is no longer available to the organiser session.
- Retains newly created judge tokens before rendering the control panel.
- Keeps live progress and reopen controls available in the same workflow.

## Safety

- Existing judge sessions, marks and submissions are preserved.
- Regeneration remains explicit for previously issued links so active links are not silently invalidated.
- Changes are limited to the `develop` release line; Production is not modified.
