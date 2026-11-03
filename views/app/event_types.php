<?php /** @var array $user, $types; ?array $edit; ?int $limit */ $atLimit = $limit !== null && count($types) >= $limit && !$edit; ?>
<h1>Event types</h1>
<p class="muted">Each event type gets its own booking link. Share the link or your page <a href="/<?= h($user['username']) ?>" target="_blank"><?= h(app_url($user['username'])) ?></a>.</p>

<?php if ($types): ?>
<table>
  <tr><th>Name</th><th>Length</th><th>Link</th><th></th></tr>
  <?php foreach ($types as $t): ?>
  <tr class="<?= $t['active'] ? '' : 'muted' ?>">
    <td><strong><?= h($t['name']) ?></strong><?= $t['active'] ? '' : ' <span class="pill off">hidden</span>' ?><br><span class="small muted"><?= h($t['description']) ?></span></td>
    <td><?= (int)$t['duration'] ?> min<?= $t['meet'] ? '<br><span class="small muted">Google Meet</span>' : '' ?></td>
    <td><a href="/<?= h($user['username']) ?>/<?= h($t['slug']) ?>" target="_blank" class="small"><?= h(app_url($user['username'] . '/' . $t['slug'])) ?></a>
        <button type="button" class="link small" onclick="navigator.clipboard.writeText(<?= h(json_encode(app_url($user['username'] . '/' . $t['slug']))) ?>);this.textContent='copied'">copy</button></td>
    <td class="nowrap">
      <a class="btn secondary mini" href="/app/event-types?edit=<?= (int)$t['id'] ?>">Edit</a>
      <form method="post" action="/app/event-types/delete" class="inline" onsubmit="return confirm('Delete this event type? Existing bookings are kept.')">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="btn danger mini">Delete</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<?php if ($atLimit): ?>
  <p class="notice err mt">The free plan includes <?= $limit ?> event type<?= $limit === 1 ? '' : 's' ?>. <a href="/app/billing">Upgrade to Pro</a> for unlimited event types.</p>
<?php else: ?>
<h2 class="mt"><?= $edit ? 'Edit event type' : 'New event type' ?></h2>
<form method="post" action="/app/event-types" class="form card">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <div class="grid2">
    <label>Name <input name="name" required maxlength="120" value="<?= h($edit['name'] ?? '') ?>" placeholder="30-minute consultation"></label>
    <label>URL slug <input name="slug" pattern="[a-z0-9-]*" maxlength="60" value="<?= h($edit['slug'] ?? '') ?>" placeholder="auto from name"></label>
    <label>Duration (minutes) <input type="number" name="duration" min="5" max="480" required value="<?= (int)($edit['duration'] ?? 30) ?>"></label>
    <label class="chk"><input type="checkbox" name="meet" value="1" <?= ($edit['meet'] ?? 1) ? 'checked' : '' ?>> Add a Google Meet link</label>
  </div>
  <label>Description <textarea name="description" rows="2" maxlength="500"><?= h($edit['description'] ?? '') ?></textarea></label>
  <label class="chk"><input type="checkbox" name="active" value="1" <?= ($edit['active'] ?? 1) ? 'checked' : '' ?>> Visible on your page</label>
  <div><button class="btn"><?= $edit ? 'Save changes' : 'Create event type' ?></button>
    <?php if ($edit): ?><a class="link" href="/app/event-types">cancel</a><?php endif; ?></div>
</form>
<?php endif; ?>
