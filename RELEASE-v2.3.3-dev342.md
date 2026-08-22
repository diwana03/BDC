# BDC v2.3.3-dev342

## Judge contact fallback

- Shows **Contact information missing** when a judge assignment has neither an email address nor a WhatsApp/phone number in the Judge Database.
- Hides only the unavailable delivery channel; a saved email or WhatsApp number remains usable independently.
- Keeps Copy Link, Open, Regenerate Link and all scoring/rescore behaviour unchanged.

## Parity Gate

- Testing Score Dashboard: Automatic Judge Links applies the same contact availability rules against isolated Test assignments.
- Live Scoring Dashboard: Automatic Judge Links applies the same contact availability rules against real judge assignments.
- Projector: unchanged because contact delivery does not affect projection data or rendering.

## Validation

- Static Test/Live marker parity, JSON parsing and `git diff --check` passed.
- PHP CLI is unavailable; this exact candidate requires deployment to Staging for final browser validation.

## Migration and deployment

- Database migration: none.
- Deployment target: GitHub `develop`; user deploys the exact commit to Staging.
- Production: unchanged and blocked pending Staging validation.
