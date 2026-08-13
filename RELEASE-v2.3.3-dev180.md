# BDC 2.3.3-dev180

## Fixed

- Opens legacy saved scoring rounds after checking and repairing required scoring fields.
- Applies the same compatibility protection to the Scoring Tests Dashboard and Live Scoring Dashboard.
- Prevents a blank HTTP 500 page when an unexpected scoring loader error occurs.
- Records a short error reference in the server log without exposing technical database details publicly.

## Safety

- Existing events, competitors, judges, marks, results and saved rounds are not deleted or reset.
- No Production deployment is included in this release.
