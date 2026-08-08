# BDC v2.3.3-dev66

## Registration Desk Link Recovery

- Automatic scoring rounds no longer leave the Registration Desk unusable when the original plain secure token is no longer present in the admin session.
- The Registration Desk card now offers **Regenerate Registration Link** when the secure token must be reissued.
- Regeneration creates a new secure token for the same event/category desk and invalidates the previous desk link.
- Once regenerated, **Copy Link** and **Open Registration Desk** are restored immediately.
- Competitors, bibs, scoring data, round status and judge data are not changed by link regeneration.
- Admin navigation changes from dev65 remain unchanged.
- No staging deployment is performed by this release commit.
