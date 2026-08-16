# BDC 2.3.3-dev202

## Judge Database and private registration link

- Adds Judge Database under the Admin People group.
- Adds generated Judge IDs such as `JDG-000123`.
- Stores identity, location, photo, Instagram and biography.
- Stores optional private email, phone, WhatsApp and preferred contact method.
- Stores Bachata/Salsa qualifications, regular/chief role, divisions, rounds, languages, experience and certification.
- Adds an unlisted public judge-profile form at `/judge-profile/`.
- Only Full Name is mandatory on the public form; email and phone numbers are optional.
- Keeps public submissions pending until an organiser approves or rejects them.
- Merges approved information into an existing name-only judge record instead of duplicating it.
- Adds complete Admin profile editing and active/inactive status.
- Does not add the judge registration page to public website navigation.

## Privacy and validation

- Contact information is displayed only inside authenticated Admin pages.
- Public submissions use CSRF protection, file-type/size validation and a bot-trap field.
- Judge selection search remains Admin-only.
- Adds a new immutable database migration; no existing migration was modified.

## Deployment

- Source release only. Deploy dev202 to Staging through Release Manager.
- Production was not deployed or modified.
