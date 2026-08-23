# BDC v2.3.3-dev384

## Final dashboard refresh repair

- Loads the existing Final pairing synchronization script on the actual Final dashboard in Testing and Live.
- Emcee-generated couples update on the open dashboard without a manual page refresh.
- The status message changes from Waiting to synchronized when every couple arrives.
- Save Pairing Draft and Confirm Final Pairing keep their existing completeness gate.
- Pairing, randomization, judge selection, scoring, projector and database logic are unchanged.
- No database migration. Production untouched pending Staging validation.
