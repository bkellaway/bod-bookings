<?php /** @var ?array $booking, $host; string $token */ ?>
<h1>Your booking</h1>
<?php if (!$booking): ?>
  <p class="notice err">This link is invalid.</p>
<?php else: ?>
  <section id="info" class="card"><p class="muted">Loading…</p></section>
  <p class="notice err hidden" id="err"></p>
  <div id="actions" class="row hidden">
    <button class="btn" id="btn-resched">Reschedule</button>
    <button class="btn secondary" id="btn-cancel">Cancel booking</button>
  </div>
  <section id="resched" class="hidden">
    <h2>Pick a new time</h2>
    <div id="picker" class="picker"></div>
    <button type="button" class="link" id="resched-back">Never mind</button>
  </section>
  <section id="confirm-cancel" class="card hidden">
    <p>Cancel this booking? The calendar event will be removed.</p>
    <button class="btn danger" id="cancel-yes">Yes, cancel it</button> <button class="btn secondary" id="cancel-no">Keep it</button>
  </section>
  <section id="done" class="done hidden"><h2 id="done-h"></h2><p id="done-p"></p></section>

<script src="/assets/picker.js"></script>
<script>
(function () {
  const T = <?= json_encode($token) ?>;
  const $ = (s) => document.querySelector(s);
  const show = (s, on = true) => $(s).classList.toggle('hidden', !on);
  let booking = null, picker = null;
  const fmt = (iso) => new Intl.DateTimeFormat(undefined, { dateStyle: 'full', timeStyle: 'short' }).format(new Date(iso));
  const tzName = () => Intl.DateTimeFormat().resolvedOptions().timeZone;

  function render() {
    $('#info').innerHTML = '<p><strong>' + esc(booking.type_name) + '</strong> with ' + esc(booking.host) + '</p>' +
      '<p>' + fmt(booking.start) + ' <span class="muted">(' + tzName() + ')</span></p>' +
      (booking.meet_link ? '<p>Google Meet: <a href="' + esc(booking.meet_link) + '">' + esc(booking.meet_link) + '</a></p>' : '');
    show('#actions', booking.status === 'confirmed');
  }
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  function fail(msg) { $('#err').textContent = msg; show('#err'); }
  async function api(path, body) {
    const r = await fetch(path + '?t=' + encodeURIComponent(T), body ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.assign({ t: T }, body)) } : {});
    const d = await r.json();
    if (!r.ok) { if (d.booking) { booking = d.booking; render(); } throw new Error(d.error || 'Request failed'); }
    return d;
  }
  api('/api/booking').then(d => { booking = d.booking; render(); }).catch(e => { fail(e.message); });

  $('#btn-resched').onclick = () => {
    show('#actions', false); show('#resched');
    if (!picker) picker = Picker.mount($('#picker'), { query: 't=' + encodeURIComponent(T), onPick: doReschedule });
  };
  $('#resched-back').onclick = () => { show('#resched', false); show('#actions'); };
  async function doReschedule(iso) {
    show('#err', false);
    try {
      const d = await api('/api/reschedule', { start: iso });
      booking = d.booking; render(); show('#resched', false); show('#actions');
      $('#done-h').textContent = 'Rescheduled'; $('#done-p').textContent = 'Updated invitation sent to ' + booking.guest + '.'; show('#done');
    } catch (e) { fail(e.message); if (/no longer available/i.test(e.message)) picker.reload(); }
  }
  $('#btn-cancel').onclick = () => { show('#actions', false); show('#confirm-cancel'); };
  $('#cancel-no').onclick  = () => { show('#confirm-cancel', false); show('#actions'); };
  $('#cancel-yes').onclick = async () => {
    show('#err', false);
    try {
      await api('/api/cancel', {});
      show('#confirm-cancel', false); show('#info', false);
      $('#done-h').textContent = 'Booking cancelled'; $('#done-p').innerHTML = 'Want a new time? <a href="/' + esc(booking.username) + '">Book again</a>.'; show('#done');
    } catch (e) { fail(e.message); }
  };
})();
</script>
<?php endif; ?>
