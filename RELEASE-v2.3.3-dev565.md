# BDC 2.3.3-dev565

## Shared-person BDC promotion

- A Super Admin-approved Bachata profile submission now allocates a BDC ID when the matched shared person currently has only an SDC identity.
- The existing shared person, SDC ID, photo and contact details are preserved.
- BDC numbering is protected by a database lock and does not reuse codes recorded in the detachment archive.
- The BDC result identity is created before Bachata role and special-category assignment.
- No profile submission bypasses the existing Super Admin approval queue.
