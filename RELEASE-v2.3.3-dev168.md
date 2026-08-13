# BDC 2.3.3-dev168

## Testing-first presentation controls

- Adds a prominent **Test Live Presentation** panel directly to the Scoring Tests Dashboard.
- Opens the Test projector, current round presentation controls, judge live progress, and Final emcee matching from the Test screen.
- Adds Drum Roll, Fireworks, Confetti, and Clear Effect buttons to the shared projector controller, so the same controls work in Testing and Live.
- Keeps Test projector data isolated from official scoring data.

## Verification

- Test presentation links use `data_mode=test` and `bdc_test_*` scoring tables.
- The Emcee Random Match action is shown only for a Test Final round.
- Testing and Live use the same projector controller and effect endpoint.

## Permanent development rule

- Scoring work is implemented on Testing first, then Live, verified for parity, and both implementations are pushed together in the same release.
- The rule is enforced by the repository instructions in `AGENTS.md` and documented in `PROJECT.md`.
