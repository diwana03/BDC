# BDC 2.3.3-dev197

## Live-style Testing panel restored

- Registers the clean `panel.php` entry as a full Scoring Tests dashboard surface.
- Applies the same Testing enhancement layer previously attached to the main Testing index.
- Restores the Manual Test badge, Live-style workflow labels, special-category controls, judge controls, and admin navigation.
- Keeps every Manual Testing action and result redirect on the clean panel instead of falling back to the old-style index URL.
- Preserves the Manual, Automatic, and Test Projector selection screen.

## Testing-only tools

- Random competitor, judge, and score generators remain available in Testing.
- Live receives no random generators or Testing reset controls.
- Testing remains isolated through `bdc_test_*` tables.

## Validation

- Repository whitespace and JavaScript syntax checks passed.
- PHP CLI is unavailable locally; Staging health checks remain the PHP validation gate.

## Deployment

- Source release only. Deploy dev197 to Staging through Release Manager.
- Production was not deployed or modified.
