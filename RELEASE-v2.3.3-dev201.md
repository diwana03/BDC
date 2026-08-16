# BDC 2.3.3-dev201

## Optional public profile details

- Removes mandatory Full Name and Email fields from public new registration.
- Removes mandatory Full Name and Email fields from public profile updates.
- Validates an email only when one is entered.
- Keeps existing name, contact details, role and division when their update fields are left blank or unselected.
- Still requires the public user to select an existing BDC profile when submitting an update.
- Allows incomplete new requests to reach Admin review; Admin must request a name before approving a new competitor.
- Does not change mandatory fields on internal Admin pages.

## Validation

- Repository whitespace and JSON validation passed.
- Public requests still pass through CSRF protection and Admin approval.
- No database migration is required.

## Deployment

- Source release only. Deploy dev201 to Staging through Release Manager.
- Production was not deployed or modified.
