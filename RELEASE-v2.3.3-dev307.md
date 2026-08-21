# BDC 2.3.3-dev307 · Build 3013

## Separate scoring access

- Replaces the single **Scoring Dashboard** navigation item with two direct Live Operations links: **Jack & Jill Scoring** and **Dance Cup Scoring**.
- Jack & Jill opens its established event-round dashboard directly.
- Dance Cup opens its separate numeric-criteria dashboard directly.
- Removes the intermediate competition-workflow selection screen.
- Provides the same separate entry points under Testing & System for Jack & Jill and isolated Test Dance Cup scoring.

## Parity Gate

- **Testing:** verified separate Jack & Jill and Dance Cup Test navigation; Dance Cup remains on isolated `bdc_test_*` data.
- **Live:** verified separate direct navigation to the existing Jack & Jill dashboard and the Dance Cup dashboard.
- **Projector:** navigation-only change; shared Test/Live projector files and behavior are unchanged.
- Candidate static validation completed; staging runtime confirmation remains pending in Release Manager.

## Migration and deployment

- Database migration: none.
- Deployment: source candidate only; no Staging or Production deployment performed.
