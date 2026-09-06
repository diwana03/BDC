# BDC v2.3.3-dev650 — 30-day admin login persistence

Build 3356

## Fix

- Successful admin two-factor verification now always creates the existing secure 30-day trusted-device login.
- Removes the unchecked opt-in checkbox that allowed a normal two-hour PHP session to be mistaken for a 30-day login.
- Stops binding the trusted-device token to the exact browser User-Agent string, so routine Chrome/browser version updates cannot invalidate an otherwise valid 30-day token.
- Keeps the random selector/token credential, database expiry, Secure + HttpOnly + SameSite=Lax cookie, role/status checks, audit trail, and immediate revocation on explicit logout.
- Existing already-revoked/expired trusted tokens are not resurrected; after deployment, one successful 2FA login creates a fresh 30-day token.

## Validation

- PHP 8.1 syntax checks on authentication and login view.
- Focused regression verifies automatic 30-day issuance, browser-update tolerance and removal of checkbox dependency.
- No database migration.
