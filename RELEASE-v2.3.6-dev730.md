# BDC 2.3.6-dev730

## Change

- Adds the BDC Official Live Display control to the Holding Screen.
- Positions the control safely at the top right on standard 16:9 and narrower displays.
- One click, Enter or Space uses the existing audience fullscreen handler.
- Preserves the centred holding logo, event title and custom holding background.

## Validation

- Static regression checks cover Holding Screen markup, placement, pointer styling, accessible keyboard semantics and integration with the existing fullscreen handler.
- Deployment: local source candidate only. Production is unchanged and no push was performed.
