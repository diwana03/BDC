# BDC 2.3.3-dev652

Build 3358

## Judge Profile editor recovery

- Restores the complete Edit Judge Profile form after the previous country-dropdown change truncated the page before the save controls.
- Restores the visible Save Judge Profile action and complete profile fields.
- Keeps Flag 1 through Flag 5 on the full canonical ISO country dataset from public/assets/flags/countries.json.
- Preserves legacy saved country values instead of silently deleting them.
- Returns the empty judge photo frame to a clean white background while preserving upload, zoom, drag, crop and remove-photo behavior.
- No scoring data, judge assignments, results or historical records are changed.

Branch: develop
