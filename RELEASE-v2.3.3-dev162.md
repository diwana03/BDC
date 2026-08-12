# BDC 2.3.3-dev162

Corrective projector release for Testing and Live.

## Fixes

- Projector tab looping now advances from the server state poll, so it works reliably without depending on a second browser timer or POST request.
- **Final Full Results · Landscape** now opens the actual Final Relative Placement scoring sheet.
- The landscape sheet shows final place, couple, every judge's placement, Chief Judge marker, and judge key.
- The layout uses the same final pairs, final marks, judges, and final ranking data as the printable Final Judge Audit/PDF workflow.
- Winner Podium remains blank until a reveal placement is selected.

## Deployment

- Branch: `develop`
- No historical migration was changed.
- No Production configuration was changed.
