# BDC v2.3.3-dev431

Build: 3137  
Date: 2026-08-26

## All-judge live status

- Shows every assigned judge continuously with clear Not Started, Scoring, Complete or Submitted status.
- Refreshes judge progress, marks and status automatically every five seconds without reloading the workspace.
- Adds a visible Refresh Live Status action and last-updated timestamp for immediate event-floor checks.
- Removes individual Judge Submit as a prerequisite for calculating or finalizing once every required mark is complete.
- Makes final Submit Scores and Lock atomically lock the competition and every completed judge sheet together.
- Keeps incomplete marks as the hard safety gate and continues requiring calculated rankings to be reviewed before final submission.
- Applies the same behavior to the unified and legacy Automatic views across Test and Live.

## Validation

- All-judge live-state regression: passed.
- Five-second and manual refresh regression: passed.
- Complete-marks final-lock regression: passed.
- Existing JavaScript regression suite: passed.
