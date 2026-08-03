# Bluehost deployment

1. Extract all ZIP contents directly into `/public_html/bachatadancecouncil/portal`.
2. Preserve the existing `config/config.php` file.
3. Visit `/portal/health.php`, then log in at `/portal/admin/` once. This runs the automatic database upgrade.
4. Open `/portal/admin/results/` to manage official PDF, CSV and World Result records.
5. Public results are available at `/portal/results/`.

For uploaded repository files, ensure `/portal/storage/results` is writable (typically 755; use 775 only if required by the host).
