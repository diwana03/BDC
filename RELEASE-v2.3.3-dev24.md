# BDC 2.3.3-dev24, build 2354

## Scoring Dashboard

- Shows only active and pending scoring rounds.
- Moves completed and archived scoring records to **Past Event Scores**.
- Restricts Past Event Scores to Admin, Master Scorer and Super Admin.
- Keeps past rounds view-only through the existing locked and archived scoring controls.

## Users and roles

- Super Admin can promote or demote existing users between Scorer, Master Scorer, Admin and Super Admin.
- Regular Scorers cannot access Past Event Scores.
- Maximum three active Super Admin accounts.
- Blocks demotion or suspension of the last active Super Admin.
- Records role creation and every role change in the audit log.

## Deployment

- Includes one dedicated migration extending the existing user-role enum.
- Preserves all dev23 identity linking, photo adjustment, email 2FA and Automated Scoring Phase 1 work.
- Deploy to Staging first. Production is unchanged.
