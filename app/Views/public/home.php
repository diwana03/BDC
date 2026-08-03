<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bachata Dance Council Portal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= e(url('/public/assets/css/app.css')) ?>?v=0.9.0" rel="stylesheet">
</head>
<body class="portal-home portal-home-compact">
<nav class="navbar navbar-expand-lg navbar-dark portal-navbar sticky-top">
  <div class="container portal-nav-container">
    <a class="navbar-brand portal-brand" href="<?= e(url('/')) ?>">
      <span class="portal-brand-logo-wrap">
        <img class="portal-brand-logo" src="<?= e(url('/public/assets/img/bdc-logo-header.png')) ?>" alt="BDC logo">
      </span>
      <span>Bachata Dance Council Portal</span>
    </a>
    <div class="ms-auto">
      <?php if ($user): ?>
        <a class="btn btn-outline-light btn-sm px-3" href="<?= e(url('/admin/')) ?>">Dashboard</a>
      <?php else: ?>
        <a class="btn btn-outline-light btn-sm px-3" href="<?= e(url('/login')) ?>">Admin Login</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<header class="portal-hero portal-hero-compact">
  <div class="container text-center">
    <div class="portal-hero-inner mx-auto">
      <h1>Bachata Dance Council Portal</h1>
      <p>Official competitor records, rankings, events and points.</p>
    </div>
  </div>
</header>

<main class="portal-main-tabs">
  <div class="container">
    <nav class="portal-tabs" aria-label="Portal sections">
      <a class="portal-tab portal-tab-active" href="<?= e(url('/leaderboard/?division=novice&role=leader')) ?>">
        <span class="portal-tab-icon">🏆</span>
        <span>Leaderboard</span>
      </a>
      <a class="portal-tab" href="<?= e(url('/results/?segment=participants')) ?>">
        <span class="portal-tab-icon">👤</span>
        <span>Participant Results</span>
      </a>
      <a class="portal-tab" href="<?= e(url('/results/?segment=repository')) ?>">
        <span class="portal-tab-icon">📄</span>
        <span>Result Repository</span>
      </a>
      <a class="portal-tab" href="<?= e(url('/register/')) ?>">
        <span class="portal-tab-icon">＋</span>
        <span>New Registration</span>
      </a>
      <a class="portal-tab" href="<?= e(url('/register/?mode=update')) ?>">
        <span class="portal-tab-icon">✎</span>
        <span>Update Profile</span>
      </a>
    </nav>

    <section class="portal-welcome-panel">
      <div>
        <div class="portal-welcome-label">Official BDC Rankings</div>
        <h2>Start with the leaderboard</h2>
        <p>View rankings by division, role, country and year, or use the tabs above to search participant records and official results.</p>
      </div>
      <a class="btn btn-gold btn-lg" href="<?= e(url('/leaderboard/?division=novice&role=leader')) ?>">Open Leaderboard</a>
    </section>

    <?php if (!empty($latestEvents) || !empty($careerLeaders)): ?>
    <section class="latest-results-section mt-4 mb-5">
      <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div><div class="portal-welcome-label">BDC Champions</div><h2 class="mb-0">Hall of Fame</h2></div>
        <a class="btn btn-outline-dark" href="<?=e(url('/results/?segment=repository'))?>">View all official results</a>
      </div>
      <div class="row g-4">
        <div class="col-12 col-xl-9">
          <div class="row g-4">
            <?php foreach ($latestEvents as $event): ?>
            <div class="col-12 col-lg-4">
              <article class="latest-event-card h-100">
                <div class="latest-event-head">
                  <div class="latest-event-date"><?=e(date('d M Y',strtotime((string)$event['event_date'])))?></div>
                  <h3><?=e($event['name'])?></h3>
                  <?php if (!empty($event['venue']) || !empty($event['location'])): ?><div class="text-muted small"><?=e($event['venue'] ?: $event['location'])?></div><?php endif; ?>
                </div>
                <div class="latest-podium-list">
                <?php foreach ([1=>['🥇','Champion'],2=>['🥈','1st Runner Up'],3=>['🥉','2nd Runner Up']] as $place=>$meta): $pair=$event['placements'][$place]??['leader'=>null,'follower'=>null]; ?>
                  <div class="latest-podium-row">
                    <div class="latest-place"><span><?=$meta[0]?></span><strong><?=$meta[1]?></strong></div>
                    <div class="latest-couple">
                      <?php foreach (['leader','follower'] as $r): $person=$pair[$r]??null; ?>
                        <?php if ($person): $photo=$person['photo_url']?:url('/public/assets/img/default-competitor.svg'); ?>
                          <a class="latest-person" href="<?=e(url('/competitor/?id='.(int)$person['competitor_id']))?>">
                            <img src="<?=e($photo)?>" alt="<?=e($person['exact_name'])?>"><span><?=e($person['exact_name'])?></span>
                          </a>
                        <?php else: ?><div class="latest-person latest-person-empty"><img src="<?=e(url('/public/assets/img/default-competitor.svg'))?>" alt=""><span>Not recorded</span></div><?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
                </div>
                <a class="btn btn-dark w-100 mt-3" href="<?=e(url('/results/?segment=repository&event='.(int)$event['id']))?>">View Full Results</a>
              </article>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <aside class="col-12 col-xl-3">
          <div class="latest-event-card h-100 career-leaders-card">
            <div class="latest-event-head">
              <div class="latest-event-date">All divisions</div>
              <h3>Career Points Leaders</h3>
              <div class="text-muted small">Total points earned across a competitor’s full BDC history.</div>
            </div>
            <ol class="list-group list-group-numbered list-group-flush career-leaders-list">
              <?php foreach ($careerLeaders as $leader): $photo=$leader['photo_url']?:url('/public/assets/img/default-competitor.svg'); ?>
                <li class="list-group-item px-0 d-flex align-items-center gap-2 bg-transparent">
                  <img class="career-leader-photo" src="<?=e($photo)?>" alt="<?=e($leader['exact_name'])?>">
                  <div class="flex-grow-1 min-w-0">
                    <a class="fw-semibold text-dark text-decoration-none d-block text-truncate" href="<?=e(url('/competitor/?id='.(int)$leader['id']))?>"><?=e($leader['exact_name'])?></a>
                    <span class="small text-muted"><?=e($leader['bdc_id'])?></span>
                  </div>
                  <span class="badge text-bg-dark rounded-pill"><?=e((string)(float)$leader['career_points'])?></span>
                </li>
              <?php endforeach; ?>
              <?php if (empty($careerLeaders)): ?><li class="list-group-item px-0 text-muted bg-transparent">No career points recorded.</li><?php endif; ?>
            </ol>
          </div>
        </aside>
      </div>
    </section>
    <?php endif; ?>
  </div>
</main>

<footer class="portal-footer">
  <div class="container d-flex flex-column flex-md-row justify-content-between gap-2">
    <span>© 2026 Bachata Dance Council</span>
    <span>Official Competitor Portal</span>
  </div>
</footer>
</body>
</html>
