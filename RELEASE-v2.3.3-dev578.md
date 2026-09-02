# BDC 2.3.3-dev578

## BDC and SDC premium competitor workspaces

- Upgrades the real Bachata J&J Competitor and Salsa J&J Competitor routes with one responsive premium council-aware design; no preview or duplicate dashboard is added.
- Preserves the existing BDC result-identity and active SDC identity data boundaries, shared-person contact/photo model, pagination, CSV export, editing and case-insensitive search.
- Limits each scoped dashboard to its own career divisions and Rising, Open and Invitational category filters.
- Corrects the scoped All Participants summary state.
- Keeps the selected BDC or SDC context through profile editing and photo adjustment and renders the correct permanent council ID instead of labelling every identity as BDC.

## Validation

- Focused BDC/SDC dashboard integrity regression: passed.
- Existing segmented-dashboard and SDC database-separation regressions: passed.
- Complete JavaScript regression suite: passed.
- Repository-wide PHP 8.1 lint: passed in GitHub candidate gate.
- Candidate diff inspection: passed; only the existing shared BDC/SDC dashboard, editor, photo flow, focused test, version and release record changed.
- Exact published `develop` commit inspection: pending promotion.

## Migration and deployment

- Database migration: none.
- Staging runtime validation: not yet performed.
- Production: not approved; blocked until this exact candidate passes Staging browser checks for both dashboards, filters, edit, photo upload/crop and return navigation.
