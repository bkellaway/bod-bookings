<?php /** @var array $host, $type; bool $connected */ ?>
<a class="back" href="/<?= h($host['username']) ?>">&larr; <?= h($host['name']) ?></a>
<header class="head">
  <h1><?= h($type['name']) ?></h1>
  <p class="muted"><?= (int)$type['duration'] ?> min with <?= h($host['name']) ?><?= $type['meet'] ? ' · Google Meet link included' : '' ?></p>
  <?php if ($type['description']): ?><p><?= nl2br(h($type['description'])) ?></p><?php endif; ?>
</header>

<?php if (!$connected): ?>
  <p class="notice err">Booking is temporarily unavailable.</p>
<?php else: ?>
<div id="picker" class="picker"></div>

<form id="form" class="form card hidden" autocomplete="on">
  <p class="selected">You picked <strong id="chosen"></strong> <button type="button" class="link" id="change">change</button></p>
  <label>Name <input name="name" required maxlength="120"></label>
  <label>Email <input name="email" type="email" required maxlength="254"></label>
  <label>Anything <?= h($host['name']) ?> should know? <textarea name="notes" rows="3" maxlength="2000"></textarea></label>
  <input name="website" class="hp" tabindex="-1" autocomplete="off">
  <p class="notice err hidden" id="err"></p>
  <button type="submit" class="btn" id="submit">Confirm booking</button>
</form>

<section id="done" class="done hidden">
  <h2>You're booked</h2>
  <p id="done-when"></p>
  <p>A calendar invitation is on its way to your email.</p>
  <p id="done-meet"></p>
  <p class="muted">Need to change it? <a id="done-manage" href="#">Reschedule or cancel</a></p>
</section>

<script src="/assets/picker.js"></script>
<script>
(function () {
  const HOST = <?= json_encode($host['username']) ?>, TYPE = <?= json_encode($type['slug']) ?>;
  const $ = (s) => document.querySelector(s);
  const picker = Picker.mount($('#picker'), { query: 'u=' + encodeURIComponent(HOST) + '&type=' + encodeURIComponent(TYPE), onPick });
  let chosen = null;
  function onPick(iso) {
    chosen = iso; $('#chosen').textContent = picker.formatFull(iso);
    $('#picker').classList.add('hidden'); $('#form').classList.remove('hidden'); $('#form [name=name]').focus();
  }
  $('#change').onclick = () => { $('#form').classList.add('hidden'); $('#picker').classList.remove('hidden'); };
  $('#form').onsubmit = async (e) => {
    e.preventDefault();
    const f = e.target, err = $('#err'), btn = $('#submit');
    err.classList.add('hidden'); btn.disabled = true; btn.textContent = 'Booking…';
    try {
      const r = await fetch('/api/book', { method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ u: HOST, type: TYPE, start: chosen, tz: picker.tz(), name: f.name.value, email: f.email.value, notes: f.notes.value, website: f.website.value }) });
      const d = await r.json();
      if (!r.ok) throw new Error(d.error || 'Booking failed');
      const b = d.booking;
      $('#form').classList.add('hidden');
      $('#done-when').textContent = picker.formatFull(b.start) + ' (' + picker.tz() + ')';
      $('#done-meet').innerHTML = b.meet_link ? 'Google Meet: <a href="' + b.meet_link + '">' + b.meet_link + '</a>' : '';
      $('#done-manage').href = b.manage_url;
      $('#done').classList.remove('hidden');
    } catch (ex) {
      err.textContent = ex.message; err.classList.remove('hidden');
      if (/no longer available/i.test(ex.message)) { picker.reload(); $('#change').click(); }
    } finally { btn.disabled = false; btn.textContent = 'Confirm booking'; }
  };
})();
</script>
<?php endif; ?>
