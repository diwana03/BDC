# BDC 2.3.3-dev594

## Judge Database search repair

- Repairs older Judge Database schemas missing the country code column that caused the directory query to fail silently.
- Restores active judge name and Judge ID suggestions in Live Judge Setup.
- Adds identical Judge Database search to Test setup and Final judge rows.
- Keeps dynamically added judge rows connected to the same suggestions.
- Removes the duplicate Live suggestion-list identifier.
