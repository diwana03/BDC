BDC Portal v0.4.1 participant profile patch

Overwrite these paths inside /public_html/bachatadancecouncil/portal:
- results/index.php
- app/Services/SchemaUpdater.php
- public/assets/css/app.css
- public/assets/img/default-competitor.svg

Do not overwrite config/config.php.

After upload, open /portal/results/ and search a participant. The schema updater automatically adds bdc_competitors.photo_url.
