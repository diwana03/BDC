# BDC v2.3.3-dev209

## Unified scoring judge assignment

- Fixes the Automatic Scoring judge form whose submission previously was not handled.
- Manual and Automatic Scoring now use the same Judge Database assignment service.
- Existing judges can be selected from searchable browser suggestions.
- Typing a new name automatically creates a minimal Judge Database profile and Judge ID.
- Assignment IDs are retained so unchanged judges keep their marks and secure scoring sessions.
- Heats, Semifinals and Finals retain the linked Judge Database identity.
- New rounds copy the linked Judge ID with the judge assignment.

## Safety

- Changes are limited to the `develop` release line.
- Production is not modified by this release.
