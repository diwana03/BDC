# v2.3.3-dev566

- Adds `wdc.remove_registration` to the signed API Changes workflow.
- Requires exact WDC identity, event and category targeting.
- Keeps the change pending until explicit Super Admin approval.
- Blocks removal when official Dance Cup results or championship points exist.
- Marks the registration withdrawn and archives the WDC identity only when no active registration remains.
