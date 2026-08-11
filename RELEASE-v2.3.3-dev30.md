# BDC 2.3.3-dev30

Build: 2360  
Date: 7 August 2026

## Fix

- Corrects the PHP namespace syntax for the Super Admin Storage Usage link.
- Restores the Admin Dashboard after login by removing the parse error in `app/Views/admin/dashboard.php`.
- Does not change authentication, scoring, results, points, or the database schema.

## Deployment

Push target: `develop`. Deploy to Staging only at `/home2/zqculgmy/public_html/bachatadancecouncil/BDC_STAGING`.
