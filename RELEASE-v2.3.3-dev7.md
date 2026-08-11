# BDC 2.3.3-dev7

## Public results restoration

- Restores the missing public `/results/` controller used by the portal navigation.
- Supports both Participant Results and Result Repository views with search and event filters.
- Routes stored HTML, PDF and CSV repository documents through the protected `result-file.php` endpoint.
- Keeps published external result URLs available while hiding draft and archived repository records.
