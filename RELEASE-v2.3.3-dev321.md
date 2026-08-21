# BDC v2.3.3-dev321

Build 3027 · Development release · 22 August 2026

## Judge selection tracker

- Keeps the YES, A1, A2 and A3 counter bar pinned near the top while judges scroll through competitors.
- Gives the tracker stronger separation and visibility on desktop and mobile.
- Updates counts immediately as selections change.
- Keeps the same required YES and alternate completion rules across Test and Live.

## Optional LATER workflow

- Treats LATER as a private review marker rather than a required scoring outcome.
- Allows submission when the required YES, A1, A2 and A3 counts are complete, even if LATER markers remain.
- Retains LATER markers with the submitted judge record for later review and audit context.
- Updates judge instructions and criteria dialogs so the behavior is clear.

## Premium dashboard

- Applies a premium navy, burgundy and champagne BDC palette.
- Improves the top bar, official white logo lockup, sidebar, metric cards, action cards, tables and hover hierarchy.
- Keeps Staging and Production environment banners visibly distinct.

## Parity and safety

- Test and Live judge validation and tracking rules match.
- Finals relative placement logic is unchanged.
- `config/config.php` and Production were not changed.

## Validation

- PHP parser, JavaScript syntax, JSON metadata and static diff checks passed.
- Runtime validation remains pending on Staging after deployment.
