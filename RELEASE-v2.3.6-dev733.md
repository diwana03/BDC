# BDC 2.3.6 dev733

## Dance Cup two row projector fit

- Fixes the second All Contestants row being cut off behind the fixed footer.
- Makes the content area consume only the remaining grid row below the category heading.
- Keeps both rows inside the existing projector safe area.
- Preserves the 5 by 2 contestant layout, 4:5 photos, numbers, names, flags and country labels.
- Adds an overflow guard so no card can cover the footer at short 16:9 viewport heights.

## Deployment

Test through `develop` first. Production remains a separate approval.
