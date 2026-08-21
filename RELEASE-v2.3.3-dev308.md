# BDC 2.3.3-dev308 · Build 3014

## Jack & Jill dashboard regression repair

- Restores the **Jack & Jill Scoring** landing page with **Manual Scoring**, **Automatic Scoring**, and **Live Projection**.
- Corrects the main dashboard and sidebar links so they open that landing page instead of bypassing it and forcing Manual mode.
- Keeps **Dance Cup Scoring** as a separate direct dashboard.
- Does not modify, migrate, reset, archive, or delete any scoring data.

## Parity Gate

- **Testing:** verified the isolated Jack & Jill Test landing page still exposes Manual Scoring, Automatic Scoring, and Test Live Projection.
- **Live:** restored the matching three-option Jack & Jill landing page and verified Manual/Automatic routes still point to the existing active dashboards.
- **Projector:** verified the Live landing page opens the established `admin/live-screen/` projector and the Test page opens `admin/live-screen/test-index.php`.
- Candidate static validation completed; Staging runtime confirmation remains pending in Release Manager.

## Migration and deployment

- Database migration: none.
- Data impact: none.
- Deployment: source candidate only; no Staging or Production deployment performed.
