# Release 2.3.3-dev607

## Non-overlapping competitor projector cards

- Replaces the horizontal identity layout that allowed text to cover portraits.
- Uses a deterministic vertical stack: photo, name, prominent bib, then flag and country.
- Gives every row fixed non-shrinking space inside dense role cards.
- Keeps the uniform card background, balanced pagination, role separation and Test/Live parity.

## Super Admin background removal

- Adds true remove.bg processing for locally stored BDC/SDC competitor photos.
- Generates a transparent PNG preview without changing the live profile.
- Requires explicit Super Admin approval before applying the preview.
- Supports discarding previews and restoring the preserved original photo.
- Validates local source paths, MIME types, source/result size and PNG signatures.
- Reads the API key only from `BDC_REMOVE_BG_API_KEY` or a protected secret file outside `public_html`.
- Audits preview generation, application and original restoration.
