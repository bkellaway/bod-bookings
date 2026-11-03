<?php /** @var string $content */ $appName = Env::get('APP_NAME', 'Bookings'); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title ?? $appName) ?></title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<main class="wrap">
  <?= $content ?>
</main>
<footer class="foot"><span class="muted">Powered by <a href="/"><?= h($appName) ?></a></span></footer>
</body>
</html>
