<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login | BDC Competitor Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:480px">
<div class="card shadow-sm"><div class="card-body p-4">
<h1 class="h4 mb-4">BDC Admin Login</h1>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post">
<input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
<?php if (\App\Core\Auth::pendingTwoFactor()): ?>
<p class="text-muted">Enter the six-digit code sent to your email.</p>
<div class="mb-3"><label class="form-label">Verification code</label><input class="form-control form-control-lg text-center" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" name="verification_code" required autofocus></div>
<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="remember_device" id="rememberDevice"><label class="form-check-label" for="rememberDevice">Remember this computer for 30 days</label></div>
<button class="btn btn-dark w-100">Verify and log in</button>
<?php else: ?>
<div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
<div class="mb-3"><label class="form-label">Password</label><input class="form-control" type="password" name="password" required></div>
<button class="btn btn-dark w-100">Log in</button>
<?php endif; ?>
</form>
</div></div>
</div>
</body>
</html>
