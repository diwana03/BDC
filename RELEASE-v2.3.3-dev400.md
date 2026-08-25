# BDC v2.3.3-dev400

Release date: 25 August 2026  
Build: 3106  
Branch: `develop`

## Complete pre-audit Special Category recovery

- Extends dev399 recovery for manually assigned categories that predate competitor audit logging.
- Reads existing BDC Database or Full backups and extracts only `bdc_competitors` and `bdc_competitor_discipline_profiles` Special Category values.
- Provides a preview with total, matched and missing competitor counts before any write.
- Matches current competitors by stable BDC ID first and legacy numeric ID only as fallback.
- Uses the legacy numeric ID only when the backup record has no BDC ID, preventing an old ID from being matched to a different competitor.
- Skips profiles already holding any valid Special Category so the earlier dev399 audit recovery and later manual corrections cannot be overwritten by an older backup.
- Creates a fresh current database safety backup before restoration.
- Restores only Bachata Rising, Bachata Open, Bachata Invitational, Salsa Rising and Salsa Open category fields.
- Never applies the complete old database and never rewrites names, contacts, photos, scores, events, registrations, points, results or publications.
- Records the backup filename, previous category and recovered category for every applied row.

## Operator workflow

1. Open `/portal/admin/competitors/special-category-recovery.php` as Super Admin.
2. Select the newest Database or Full backup created before dev397 deployment.
3. Preview and confirm `Total in backup` is close to the expected 150 assignments; `Remaining to restore` is the only count that will be written.
4. Review missing competitors before restoration.
5. Type `RESTORE SPECIAL CATEGORIES` and apply the targeted recovery.
6. Verify the Special Category count and several known Bachata/Salsa profiles.

## Validation and deployment

- Automated gate: `node tests/special-category-backup-recovery-v400.js`.
- Complete JavaScript regression suite required before publication.
- PHP/database runtime: not tested from Codex.
- Migration: `20260825_0400_special_category_backup_recovery.php`.
- Production approval remains blocked until Staging previews a real pre-dev397 backup and validates the recovered counts.
