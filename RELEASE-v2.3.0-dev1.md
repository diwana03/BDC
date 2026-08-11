# BDC 2.3.0-dev1

Development Release Dashboard preview.

## Workflow

1. Check GitHub `develop` from the dashboard.
2. Select an exact commit and deploy it to Staging.
3. Test Staging. A successful automated health check marks only that commit as Passed.
4. Approve the exact passed commit.
5. Keep Production deployment disabled until the Staging workflow is fully tested.

Failed releases remain in history. A later release can be selected and tested independently.

## Safety

- Super Admin authentication and CSRF validation are required.
- Jobs are serialized with both a database queue and worker lock.
- The dashboard currently exposes Staging deployment only.
- There is no Production deployment button in this preview.
- Configuration, storage, uploads and published results are preserved during Staging deployment.
- Migrations must remain forward-compatible and immutable.

## One-time server activation

1. Add the `deployment` block from `config/config.example.php` to the protected Staging `config/config.php`, using the actual Bluehost paths and health URLs.
2. Set `deployment.enabled` to `true`.
3. Replace the old automatic `deploy_bdc_staging.sh` cron with:

```cron
* * * * * php /home2/zqculgmy/public_html/bachatadancecouncil/BDC_STAGING/bin/deployment-worker.php >> /home2/zqculgmy/deployment_logs/bdc_deployment_worker.log 2>&1
```

After this one-time activation, Development releases can be selected and deployed to Staging from **Admin → Release Manager**. Production deployment remains unavailable.
