# BDC v2.3.3-dev671 — Projector CSS cache delivery fix

Build 3377

Replaces the hard-coded projector CSS query versions in live-display/feed.php with automatic file modification timestamps for responsive, theme, roster and safe-area stylesheets. This ensures the already-present dev670 audience-readable Heats typography is actually delivered after deployment and prevents future projector CSS changes from being hidden by browser or intermediary caches. No scoring or ranking logic changed.
