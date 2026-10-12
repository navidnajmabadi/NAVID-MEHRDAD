// Nakilab — page behaviour: intro, nav, reveals, and the intake form.
(() => {
  document.getElementById('year').textContent = new Date().getFullYear();
  const start = () => document.documentElement.classList.add('loaded');
  requestAnimationFrame(() => requestAnimationFrame(start));
  setTimeout(start, 120); // rAF is paused in background tabs

  const nav = document.querySelector('.nav');
  const onScroll = () => nav.classList.toggle('scrolled', scrollY > 40);
  addEventListener('scroll', onScroll, { passive: true }); onScroll();

  const io = new IntersectionObserver(es => es.forEach(e => {
    if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
  }), { rootMargin: '0px 0px -10% 0px' });
  document.querySelectorAll('.reveal').forEach(el => io.observe(el));

  /* ---------- Intake form ---------- */
  const form = document.getElementById('intake-form');
  const status = document.getElementById('form-status');
  const groups = [...form.querySelectorAll('[data-for]')];
  const setType = type => {
    const radio = form.querySelector(`input[name="type"][value="${type}"]`);
    if (radio) radio.checked = true;
    groups.forEach(g => { g.hidden = g.dataset.for !== type; });
  };
  form.addEventListener('change', e => { if (e.target.name === 'type') setType(e.target.value); });
  // Buttons around the page ("Request an idea", "Investor inquiry") preselect the form type
  document.querySelectorAll('a[data-type]').forEach(a => a.addEventListener('click', () => setType(a.dataset.type)));

  // Required fields per type: [name, message]
  const REQUIRED = {
    common: [['name', 'Please enter your name.'], ['email', 'Please enter a valid email.']],
    have: [['title', 'Give your idea a name.'], ['idea', 'Tell us a little about the idea.']],
    need: [['skills', 'Tell us your skills.']],
    invest: [['message', 'Please add a short message.']],
  };
  const showErr = (name, msg) => {
    const input = form.elements[name], err = document.getElementById('e-' + name);
    if (input && input.setAttribute) input.setAttribute('aria-invalid', msg ? 'true' : 'false');
    if (err) err.textContent = msg || '';
  };
  form.addEventListener('input', e => { if (e.target.name) showErr(e.target.name, ''); });

  form.addEventListener('submit', async e => {
    e.preventDefault();
    status.textContent = '';
    const type = form.elements.type.value;
    let first = null;
    for (const [name, msg] of [...REQUIRED.common, ...REQUIRED[type]]) {
      const el = form.elements[name];
      const bad = !el.value.trim() || (name === 'email' && !el.checkValidity());
      showErr(name, bad ? msg : '');
      if (bad && !first) first = el;
    }
    const consent = form.elements.consent.checked;
    document.getElementById('e-consent').textContent = consent ? '' : 'Please tick the box so we can reply to you.';
    if (!consent && !first) first = form.elements.consent;
    if (first) { first.focus(); return; }

    const data = { type, consent: true, website: form.elements.website.value };
    const fields = ['name', 'email', ...{ have: ['title', 'idea', 'stage', 'country'], need: ['skills', 'interests', 'budget', 'time'], invest: ['org', 'message'] }[type]];
    fields.forEach(f => { data[f] = form.elements[f].value.trim(); });
    if (type === 'have') data.help = [...form.querySelectorAll('input[name="help"]:checked')].map(c => c.value);

    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true; btn.firstChild.textContent = 'Sending… ';
    try {
      const res = await fetch('api/intake.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
      const out = await res.json().catch(() => ({}));
      if (!res.ok || !out.ok) throw new Error(out.error || 'Something went wrong. Please try again.');
      const box = document.getElementById('intake-box');
      box.innerHTML = `<div class="form-done" role="status" tabindex="-1">
        <div class="tick"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg></div>
        <h3>Received</h3><p>Thank you. We'll review it and get back to you.</p></div>`;
      box.firstElementChild.focus();
    } catch (err) {
      status.textContent = err.message === 'Failed to fetch' ? 'Connection problem. Please try again.' : err.message;
      btn.disabled = false; btn.firstChild.textContent = 'Submit ';
    }
  });
})();
