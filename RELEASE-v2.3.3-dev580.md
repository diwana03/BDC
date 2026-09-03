# BDC 2.3.3-dev580

## WDC competitor profile editor

- Replaces the free-text WDC country field with the canonical country selector and preserves the stored selection.
- Adds city, primary contact, email, phone, WhatsApp, Instagram, studio or team, partner or team member names, public biography and internal admin notes.
- Aligns profile labels with the connected Asia Open Dance Cup registration form and records photo-posting permission.
- Replaces the permanent entry-type grey-out with a Super Admin-only Lock/Unlock correction control and audit trail; an approved correction updates only the duplicated registration entry classification atomically, never its event or category assignment.
- Backfills missing solo WDC country and contact values from the already linked shared person record without changing that shared BDC or SDC record.
- Keeps the permanent WDC ID and existing entry type locked after identity creation.
- Keeps category registrations, scoring, official results and championship points read-only and unchanged.
- Retains the existing official-history protection against archival.

## Validation

- Static WDC editor and migration regression test.
- Full JavaScript regression suite.
