# BDC 2.3.3-dev205

## Users and roles usability

- Renames the ambiguous **Edit** action to **Edit User & Role**.
- Opens the populated role editor directly instead of leaving the administrator at the user table.
- Displays the selected administrator's name and email above the editor.
- Highlights the user currently being edited.
- Keeps role, status, password reset and permission editing in the same form.

## Judge Directory actions

- Renames the judge-row action to **Edit Profile & Photo**.
- Shows a direct **Adjust Photo** action when the judge already has a photo.
- Shows **View only** instead of an edit action when the administrator lacks judge-edit permission.

## Judge country flags

- Adds a country selector with common judge countries to the judge editor.
- Displays a flag preview beside the judge country field.
- Displays the judge's flag directly beside their name in the Judge Directory.
- Continues allowing a country to be typed when it is not in the suggested list.

## Projector country flags

- Shows judge flags on both Testing and Live judge projector screens.
- Shows competitor flags on competitor, callback and finalist screens.
- Shows both partners' flags on random matching, podium and final-ranking screens.
- Adds flags to the landscape Final Relative Placement sheet for couples and the judge key.
- Uses blank fallback behavior for legacy records that do not have country data.

## Validation and deployment

- JSON and whitespace validation completed.
- No database migration or configuration change is required.
- Staging deployment: not performed.
- Production deployment: not performed.
