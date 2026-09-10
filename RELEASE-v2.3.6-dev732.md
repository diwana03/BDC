# BDC 2.3.6 dev732

## Dance Cup WDC country projection repair

- Fixes newly added Dance Cup contestants showing no country on projection.
- Reads the linked WDC identity country before the optional legacy BDC competitor profile.
- Preserves multiple countries, real flag images and full country names.
- Applies to Contestant Call, All Contestants, Live Scoreboard and Winner Podium.
- Adds WDC country data to the live identity revision so an open projector refreshes after a country update.
- Updates projection diagnostics to treat a linked WDC identity as the valid Dance Cup profile source.

## Included

Includes the unpushed dev731 Dance Cup Judge Call sequence.

## Deployment

Test through `develop` first. Production remains a separate approval.
