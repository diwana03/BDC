# BDC 2.3.3-dev311 · Build 3017

## Clarify registration and Flight setup

- Moves **Registration Desk** and **Flights** above the Leader and Follower entry boards in Automatic Scoring.
- Gives Registration Desk an amber setup treatment and Flights a blue operational treatment so neither control is lost among neutral cards.
- Strengthens the existing role language with a blue Leader board and red Follower board.
- Applies the same Registration Desk and Flight hierarchy to Manual Live scoring and isolated Test scoring.
- Corrects the registration guidance so it now points to the participant boards below.

## Included iframe correction

- Prevents the universal **Back / Dashboard** navigation from appearing inside embedded judge-control and scoring iframes.
- Keeps the same navigation available on standalone pages.

## Parity Gate

- **Testing:** Test Flights use the same prominent blue treatment and inherited registration remains clearly locked.
- **Live:** Manual and Automatic setup controls share the same amber/blue hierarchy.
- **Projector:** no projection rendering or scoring logic changed; the iframe navigation guard remains shared.
- Candidate static validation completed; Staging runtime confirmation remains pending.

## Migration and deployment

- Database migration: none.
- Data impact: none.
- Deployment: source candidate only; no Staging or Production deployment performed.
