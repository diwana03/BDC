# BDC v2.3.3-dev381

## Final Judge save integrity

- Replaces the old Testing-only Final save routine with the same protected assignment service used by Live.
- Blocks duplicate judges immediately by normalized name or Judge Database profile.
- Keeps server-side duplicate protection as the authoritative safety gate.
- Shows save errors and success beside Final Judge Selection.
- Returns the scorer to the same panel after saving.
- Preserves minimum three judges, exactly one Chief, Chief-first ordering and Judge Database identity.
- Applies to Manual and Automatic Finals. No matching, scoring, calculation, result or projector logic changed.
- No database migration. Production untouched pending Staging validation.
