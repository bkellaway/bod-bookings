<?php /** @var string $content */ $appName = Env::get('APP_NAME', 'Bookings'); $me = Auth::user(); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title ?? $appName) ?></title>
<link rel="stylesheet" href="/assets/style.css">
<script>try{document.cookie='tz='+Intl.DateTimeFormat().resolvedOptions().timeZone+';path=/;max-age=31536000;SameSite=Lax'}catch(e){}</script>
</head>
<body>
<header class="nav">
  <a class="brand" href="/"><?= h($appName) ?></a>
  <nav>
    <a href="/pricing">Pricing</a>
    <?php if ($me): ?><a href="/app" class="btn mini">Dashboard</a>
    <?php else: ?><a href="/login" class="btn mini">Sign in</a><?php endif; ?>
  </nav>
</header>
<main class="wrap">
  <?php foreach (take_flashes() as [$type, $msg]): ?><p class="notice <?= $type ?>"><?= h($msg) ?></p><?php endforeach; ?>
  <?= $content ?>
</main>
<footer class="foot">
  <span>&copy; <?= date('Y') ?> Back Office Designs</span>
  <a href="/privacy">Privacy</a> <a href="/terms">Terms</a> <a href="mailto:<?= h(Env::get('MAIL_FROM', '')) ?>">Contact</a>
</footer>
</body>
</html>
