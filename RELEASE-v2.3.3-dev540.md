# BDC v2.3.3-dev540

- Adds a Super Admin-only profile integration credential control.
- Stores file-managed HMAC credentials outside the public application directory.
- Downloads a newly generated credential once and never renders it in page HTML or stores it in the database.
- Keeps externally managed environment credentials authoritative and supports immediate rotation of file-managed credentials.
- Does not change profile review, points, results or scoring behavior.
