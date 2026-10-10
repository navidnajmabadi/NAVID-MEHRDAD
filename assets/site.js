// Nakilab — shared page behaviour (intro, reveals, tilt, spotlight, form)
(() => {
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const page = location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.switcher a').forEach(a => { if (a.getAttribute('href') === page) a.setAttribute('aria-current', 'page'); });
  document.getElementById('year').textContent = new Date().getFullYear();
  const start = () => document.documentElement.classList.add('loaded');
  requestAnimationFrame(() => requestAnimationFrame(start));
  setTimeout(start, 120); // rAF is paused in background tabs

  /* Reveal on scroll */
  const io = new IntersectionObserver(es => es.forEach(e => {
    if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
  }), { rootMargin: '0px 0px -12% 0px' });
  document.querySelectorAll('.reveal').forEach(el => io.observe(el));

  /* 3D tilt toward the pointer */
  if (!reduce && matchMedia('(hover: hover)').matches) document.querySelectorAll('[data-tilt]').forEach(el => {
    el.addEventListener('pointermove', e => {
      const r = el.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
      el.style.setProperty('--ry', (x * 10).toFixed(2) + 'deg');
      el.style.setProperty('--rx', (-y * 8).toFixed(2) + 'deg');
    });
    el.addEventListener('pointerleave', () => { el.style.setProperty('--rx', '0deg'); el.style.setProperty('--ry', '0deg'); });
  });

  /* Card spotlight follows the pointer */
  document.querySelectorAll('.card').forEach(c => c.addEventListener('pointermove', e => {
    const r = c.getBoundingClientRect();
    c.style.setProperty('--mx', e.clientX - r.left + 'px');
    c.style.setProperty('--my', e.clientY - r.top + 'px');
  }));

  /* Floating hero cards drift with the pointer */
  const fcards = [...document.querySelectorAll('.fcard')];
  if (!reduce && fcards.length) addEventListener('pointermove', e => {
    const x = e.clientX / innerWidth - .5, y = e.clientY / innerHeight - .5;
    fcards.forEach(c => { const d = +c.dataset.depth; c.style.translate = `${-x * 24 * d}px ${-y * 18 * d}px`; });
  }, { passive: true });

  /* Consultation form: no backend yet, so it opens a pre-filled email */
  const form = document.getElementById('book-form');
  form.addEventListener('submit', e => {
    e.preventDefault();
    const f = new FormData(form);
    if (!f.get('name') || !form.querySelector('#f-email').checkValidity() || !f.get('email')) {
      (f.get('name') ? form.querySelector('#f-email') : form.querySelector('#f-name')).focus();
      return;
    }
    const body = `Name: ${f.get('name')}\nEmail: ${f.get('email')}\nNeed: ${f.get('type')}\n\n${f.get('message') || ''}`;
    location.href = `mailto:hello@nakilab.com?subject=${encodeURIComponent('Consultation request — ' + f.get('name'))}&body=${encodeURIComponent(body)}`;
  });
})();
