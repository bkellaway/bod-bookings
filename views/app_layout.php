<?php /** @var string $content */ /** @var array $user */ $appName = Env::get('APP_NAME', 'Bookings');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$nav = ['/app' => 'Bookings', '/app/event-types' => 'Event types', '/app/availability' => 'Availability', '/app/settings' => 'Settings', '/app/billing' => 'Billing'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title ?? '') ?> — <?= h($appName) ?></title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="app">
<header class="nav">
  <a class="brand" href="/app"><?= h($appName) ?></a>
  <nav class="appnav">
    <?php foreach ($nav as $href => $label): ?>
      <a href="<?= $href ?>" class="<?= $path === $href ? 'on' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="me">
    <a href="/<?= h($user['username']) ?>" target="_blank" title="Your public page">/<?= h($user['username']) ?></a>
    <span class="pill <?= is_pro($user) ? '' : 'off' ?>"><?= is_pro($user) ? 'Pro' : 'Free' ?></span>
    <form method="post" action="/logout"><?= csrf_field() ?><button class="link">Sign out</button></form>
  </div>
</header>
<main class="wrap">
  <?php foreach (take_flashes() as [$type, $msg]): ?><p class="notice <?= $type ?>"><?= h($msg) ?></p><?php endforeach; ?>
  <?= $content ?>
</main>
</body>
</html>
