// Nakilab — tiny 2D particle engine for the Particles theme.
// Each <canvas data-dots="mode"> gets dots that spring toward a moving set of target points.
const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
const TAU = Math.PI * 2;
const rnd = (a, b) => a + Math.random() * (b - a);

// Each mode returns targets for dot i at time t, in a -1..1 box (y up), plus brightness 0..1.
const MODES = {
  // a wireframe cube made of dots, turning in 3D
  cube(n) {
    const E = [[0,1],[1,3],[3,2],[2,0],[4,5],[5,7],[7,6],[6,4],[0,4],[1,5],[2,6],[3,7]];
    const V = [...Array(8)].map((_, i) => [(i & 1) ? 1 : -1, (i & 2) ? 1 : -1, (i & 4) ? 1 : -1]);
    const per = Math.ceil(n / 12);
    return (i, t) => {
      const e = E[i % 12], u = Math.floor(i / 12) / per;
      const a = V[e[0]], b = V[e[1]];
      let x = a[0] + (b[0] - a[0]) * u, y = a[1] + (b[1] - a[1]) * u, z = a[2] + (b[2] - a[2]) * u;
      const ry = t * .5, rx = -.45;
      [x, z] = [x * Math.cos(ry) - z * Math.sin(ry), x * Math.sin(ry) + z * Math.cos(ry)];
      [y, z] = [y * Math.cos(rx) - z * Math.sin(rx), y * Math.sin(rx) + z * Math.cos(rx)];
      const k = 2.6 / (4 + z);
      return [x * k * .55, y * k * .55, .45 + (1 - (z + 1.7) / 3.4) * .55];
    };
  },
  // scattered dots assemble into a tidy app grid, hold, then scatter again
  assemble(n) {
    const side = Math.ceil(Math.sqrt(n)), scatter = [...Array(n)].map(() => [rnd(-1, 1), rnd(-.9, .9)]);
    return (i, t) => {
      const ph = (t / 5) % 1, on = ph < .62;
      const gx = (i % side) / (side - 1) * 1.1 - .55, gy = Math.floor(i / side) / (side - 1) * 1.1 - .55;
      const s = scatter[i];
      return on ? [gx, gy, .85] : [s[0], s[1], .45];
    };
  },
  // a disc splits in two and rejoins — breaking a problem down
  split(n) {
    const pts = [...Array(n)].map(() => { const a = Math.random() * TAU, r = Math.sqrt(Math.random()) * .6; return [Math.cos(a) * r, Math.sin(a) * r]; });
    return (i, t) => {
      const [x, y] = pts[i], gap = Math.max(0, Math.sin(t * 1.2)) * .35;
      return [x + (x < 0 ? -gap : gap), y, .55 + (x < 0 ? .1 : .35)];
    };
  },
  // a trail of light launching along a curve
  stream(n) {
    const off = [...Array(n)].map(() => [Math.random(), rnd(-.05, .05), rnd(-.05, .05)]);
    return (i, t) => {
      const [o, jx, jy] = off[i], u = (o + t * .25) % 1;
      const x = -.85 + u * 1.7, y = -.75 + Math.pow(u, 2.2) * 1.5;
      return [x + jx * u, y + jy * u, Math.pow(u, 1.5)];
    };
  },
  // a heart that beats
  heart(n) {
    const pts = [];
    while (pts.length < n) {
      const x = rnd(-1.2, 1.2), y = rnd(-1.1, 1.3);
      if (Math.pow(x * x + y * y - 1, 3) - x * x * y * y * y < 0) pts.push([x * .5, y * .5]);
    }
    return (i, t) => { const s = 1 + Math.pow(Math.max(0, Math.sin(t * 2.6)), 8) * .12; return [pts[i][0] * s, pts[i][1] * s - .05, .75]; };
  },
  // the RingRingPaw paw, pulsing like a ping
  paw(n) {
    const pads = [[0, -.22, .36, .3], [-.44, .26, .15, .2], [-.17, .55, .15, .2], [.17, .55, .15, .2], [.44, .26, .15, .2]];
    const pts = [...Array(n)].map((_, i) => {
      const [cx, cy, rx, ry] = pads[i % 5 === 0 ? 0 : (Math.random() < .55 ? 0 : 1 + ((Math.random() * 4) | 0))];
      const a = Math.random() * TAU, d = Math.sqrt(Math.random());
      return [cx + Math.cos(a) * rx * d, cy + Math.sin(a) * ry * d - .1];
    });
    return (i, t) => { const s = 1 + Math.sin(t * 2) * .03; return [pts[i][0] * s, pts[i][1] * s, .8]; };
  },
  // dots flowing round a wheel through three engines
  flow(n) {
    const off = [...Array(n)].map(() => [Math.random(), rnd(-.03, .03)]);
    return (i, t) => {
      const a = (off[i][0] + t * .06) * TAU, r = .72 + off[i][1];
      const near = Math.max(...[0, 1, 2].map(k => Math.cos(a - k * TAU / 3 - TAU / 4)));
      return [Math.cos(a) * r, Math.sin(a) * r * .5, .3 + Math.pow(Math.max(0, near), 12) * .7];
    };
  },
  // an ambient field of dots drifting in a wave
  field(n) {
    const cols = Math.ceil(Math.sqrt(n * 2)), rows = Math.ceil(n / cols);
    return (i, t) => {
      const cx = (i % cols) / (cols - 1) * 2 - 1, cy = Math.floor(i / cols) / (rows - 1) * 2 - 1;
      const w = Math.sin(cx * 3 + t * .8) * Math.cos(cy * 2 + t * .6);
      return [cx * 1.05, cy * 1.05 + w * .03, .15 + (w + 1) * .2];
    };
  },
};

export function dotfx(cv) {
  const mode = cv.dataset.dots, ctx = cv.getContext('2d');
  const color = cv.dataset.color || '150,190,255';
  const n = +(cv.dataset.n || 260), aspect = cv.dataset.aspect === 'wide', scale = +(cv.dataset.scale || 1) * .9;
  const target = MODES[mode](n);
  const P = [...Array(n)].map(() => ({ x: rnd(-1, 1), y: rnd(-1, 1), vx: 0, vy: 0, a: 0 }));
  let W = 0, H = 0, dpr = 1, mx = 9, my = 9, on = false;
  const resize = () => { dpr = Math.min(devicePixelRatio, 2); W = cv.clientWidth; H = cv.clientHeight; cv.width = W * dpr; cv.height = H * dpr; };
  new ResizeObserver(resize).observe(cv); resize();
  cv.addEventListener('pointermove', e => { const r = cv.getBoundingClientRect(); mx = ((e.clientX - r.left) / W) * 2 - 1; my = -(((e.clientY - r.top) / H) * 2 - 1); });
  cv.addEventListener('pointerleave', () => { mx = my = 9; });
  const t0 = performance.now();
  function frame(now) {
    const t = reduce ? 1.2 : (now - t0) / 1000;
    const sx = aspect ? W / 2 : Math.min(W, H) / 2, sy = aspect ? H / 2 : Math.min(W, H) / 2;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, W, H);
    for (let i = 0; i < n; i++) {
      const p = P[i], [tx, ty, ta] = target(i, t);
      if (reduce) { p.x = tx; p.y = ty; }
      else {
        p.vx += (tx - p.x) * .05; p.vy += (ty - p.y) * .05;
        const dx = p.x - mx, dy = p.y - my, d2 = dx * dx + dy * dy;
        if (d2 < .08) { p.vx += dx * .02 / (d2 + .01); p.vy += dy * .02 / (d2 + .01); } // pointer pushes dots away
        p.vx *= .82; p.vy *= .82; p.x += p.vx; p.y += p.vy;
      }
      p.a += (ta - p.a) * .1;
      const X = W / 2 + p.x * sx * scale, Y = H / 2 - p.y * sy * scale;
      ctx.fillStyle = `rgba(${color},${p.a})`;
      ctx.beginPath(); ctx.arc(X, Y, .8 + p.a * 1.3, 0, TAU); ctx.fill();
    }
    if (on && !reduce) requestAnimationFrame(frame);
  }
  if (reduce) { frame(0); return; }
  new IntersectionObserver(([e]) => { on = e.isIntersecting; if (on) requestAnimationFrame(frame); }).observe(cv);
}

document.querySelectorAll('canvas[data-dots]').forEach(dotfx);
