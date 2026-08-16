# BDC 2.3.3-dev204

## Judge permissions and editing

- Adds `View judge database` to Users & Roles permissions.
- Adds `Edit judge profiles and photos` to Users & Roles permissions.
- Requires view permission to open the Judge Database.
- Requires edit permission to create, approve or reject judge profiles.
- Requires edit permission to edit judge details, upload/remove photos, and crop/reposition photos.
- Shows the Judge Database navigation item only to permitted administrators.
- Super Admin retains full access automatically.

## Photo workflow

- The existing row-level **Edit** action opens the complete judge editor.
- The editor now visibly includes judge photo upload, removal, and **Adjust photo** after an image is uploaded.

## Validation and deployment

- JSON and whitespace validation completed.
- No database migration or configuration change is required.
- Staging deployment: not performed.
- Production deployment: not performed.
