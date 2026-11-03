<?php /** @var array $host, $types; bool $connected */ ?>
<div class="narrow">
  <h1><?= h($host['name']) ?></h1>
  <?php if ($host['welcome_text']): ?><p><?= nl2br(h($host['welcome_text'])) ?></p><?php endif; ?>
  <p class="muted">Pick a meeting type. Times are shown in your local timezone.</p>
  <?php if (!$connected): ?><p class="notice err">Booking is temporarily unavailable.</p>
  <?php elseif (!$types): ?><p class="notice err">No meeting types are available right now.</p>
  <?php else: ?>
  <ul class="types">
    <?php foreach ($types as $t): ?>
    <li><a href="/<?= h($host['username']) ?>/<?= h($t['slug']) ?>">
      <strong><?= h($t['name']) ?></strong>
      <span class="muted"><?= (int)$t['duration'] ?> min<?= $t['meet'] ? ' · Google Meet' : '' ?></span>
      <?php if ($t['description']): ?><small><?= h($t['description']) ?></small><?php endif; ?>
    </a></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
