# BDC 2.3.3-dev288 · Build 2994

## Heats and Semifinal judge guidance

- Adds the same instructions to the opening Judging Criteria screen and the top of the active judge scoring form.
- Applies to both Test and Live Automatic judge forms.
- Reads the round tier dynamically instead of hard-coding Top 10.
- Shows the configured YES quota as the required Top N dancers.
- Converts A1, A2 and A3 into the next three ordinal placements automatically.
- Explains LATER as temporary consideration and requires every LATER selection to be cleared before submission.
- Renders YES, Top N, placement values, LATER and NO in bold without displaying literal asterisks.
- Uses the correct Heats or Semifinal heading while leaving Final instructions unchanged.

## Projector effect sound upgrade

- Replaces raw single-oscillator beeps with compressed, layered Web Audio cues.
- Synchronizes five countdown impacts with the on-screen 5–4–3–2–1 sequence and adds a final reveal blast.
- Adds launch, explosion and crackle layers for fireworks.
- Adds an accelerating snare-style drum roll and final impact.
- Adds brighter celebration, gold-rain, laser and champion cues.
- Keeps public projection muted unless sound is explicitly enabled from Projection Control.
- Requires no external audio downloads or database migration.

## Deployment

- Database migration: none.
- Push target: `develop`.
- Production deployment: not performed.
