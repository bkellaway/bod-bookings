/* Slot picker: month grid + times, rendered in the guest's chosen timezone. */
window.Picker = (function () {
  const pad = (n) => String(n).padStart(2, '0');

  function partsIn(date, tz) {
    const p = {};
    new Intl.DateTimeFormat('en-US', { timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false })
      .formatToParts(date).forEach(x => { p[x.type] = x.value; });
    return p;
  }
  const dayKey = (date, tz) => { const p = partsIn(date, tz); return `${p.year}-${p.month}-${p.day}`; };

  function mount(root, opts) {
    let tz = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    const today = new Date();
    let view = { y: today.getFullYear(), m: today.getMonth() };  // month being displayed
    let slots = [];            // ISO strings from server
    let byDay = {};            // key -> [iso]
    let selectedDay = null;
    let windowEnd = null;

    root.innerHTML = `
      <div class="sch">
        <div class="sch-cal">
          <div class="sch-nav"><button type="button" class="link" data-nav="-1">&lsaquo;</button><strong class="sch-month"></strong><button type="button" class="link" data-nav="1">&rsaquo;</button></div>
          <div class="sch-grid"></div>
          <label class="sch-tz">Timezone <select class="sch-tzsel"></select></label>
        </div>
        <div class="sch-times"><p class="muted">Select a date to see available times.</p></div>
      </div>`;
    const $ = (s) => root.querySelector(s);

    // Timezone selector
    const sel = $('.sch-tzsel');
    const zones = (typeof Intl.supportedValuesOf === 'function') ? Intl.supportedValuesOf('timeZone') : [tz];
    if (!zones.includes(tz)) zones.push(tz);
    zones.forEach(z => { const o = document.createElement('option'); o.value = o.textContent = z; sel.appendChild(o); });
    sel.value = tz;
    sel.onchange = () => { tz = sel.value; index(); renderGrid(); renderTimes(); };

    root.querySelectorAll('[data-nav]').forEach(b => b.onclick = () => {
      view.m += +b.dataset.nav;
      if (view.m < 0) { view.m = 11; view.y--; } if (view.m > 11) { view.m = 0; view.y++; }
      load();
    });

    async function load() {
      $('.sch-grid').innerHTML = '<p class="muted">Loading…</p>';
      const from = `${view.y}-${pad(view.m + 1)}-01`;
      const to = `${view.y}-${pad(view.m + 1)}-${pad(new Date(view.y, view.m + 1, 0).getDate())}`;
      try {
        const r = await fetch(`/api/slots?${opts.query}&from=${from}&to=${to}`);
        const d = await r.json();
        if (!r.ok) throw new Error(d.error || 'Could not load times');
        slots = d.slots; windowEnd = d.window ? new Date(d.window[1]) : null;
      } catch (e) {
        slots = []; $('.sch-grid').innerHTML = `<p class="notice err">${e.message}</p>`; return;
      }
      index(); renderGrid(); renderTimes();
    }

    function index() {
      byDay = {};
      slots.forEach(iso => { const k = dayKey(new Date(iso), tz); (byDay[k] ||= []).push(iso); });
      if (selectedDay && !byDay[selectedDay]) selectedDay = null;
    }

    function renderGrid() {
      $('.sch-month').textContent = new Date(view.y, view.m, 1).toLocaleString(undefined, { month: 'long', year: 'numeric' });
      const first = new Date(view.y, view.m, 1).getDay();
      const days = new Date(view.y, view.m + 1, 0).getDate();
      let html = ['S', 'M', 'T', 'W', 'T', 'F', 'S'].map(d => `<span class="sch-dow">${d}</span>`).join('');
      for (let i = 0; i < first; i++) html += '<span></span>';
      const todayKey = dayKey(new Date(), tz);
      for (let d = 1; d <= days; d++) {
        const k = `${view.y}-${pad(view.m + 1)}-${pad(d)}`;
        const has = !!byDay[k];
        html += `<button type="button" class="sch-day${has ? ' has' : ''}${k === selectedDay ? ' sel' : ''}${k === todayKey ? ' today' : ''}" data-day="${k}" ${has ? '' : 'disabled'}>${d}</button>`;
      }
      $('.sch-grid').innerHTML = html;
      root.querySelectorAll('.sch-day.has').forEach(b => b.onclick = () => { selectedDay = b.dataset.day; renderGrid(); renderTimes(); });
      $('[data-nav="-1"]').disabled = (view.y < today.getFullYear()) || (view.y === today.getFullYear() && view.m <= today.getMonth());
      $('[data-nav="1"]').disabled = windowEnd ? new Date(view.y, view.m + 1, 1) > windowEnd : false;
    }

    function renderTimes() {
      const box = $('.sch-times');
      if (!selectedDay) { box.innerHTML = `<p class="muted">${slots.length ? 'Select a date to see available times.' : 'No availability this month.'}</p>`; return; }
      const list = byDay[selectedDay] || [];
      const heading = new Date(list[0]).toLocaleDateString(undefined, { timeZone: tz, weekday: 'long', month: 'long', day: 'numeric' });
      box.innerHTML = `<h3>${heading}</h3>` + list.map(iso =>
        `<button type="button" class="sch-slot" data-iso="${iso}">${formatTime(iso)}</button>`).join('');
      box.querySelectorAll('.sch-slot').forEach(b => b.onclick = () => opts.onPick(b.dataset.iso));
    }

    const formatTime = (iso) => new Date(iso).toLocaleTimeString(undefined, { timeZone: tz, hour: 'numeric', minute: '2-digit' });
    const formatFull = (iso) => new Date(iso).toLocaleString(undefined, { timeZone: tz, weekday: 'long', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit', timeZoneName: 'short' });

    load();
    return { reload: load, tz: () => tz, formatFull };
  }

  return { mount };
})();
