// Nakilab — shared three.js toolkit: stage setup, shapes, and the 3D scenes used by the three page themes.
import * as THREE from 'three';
import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js';
import { RenderPass } from 'three/addons/postprocessing/RenderPass.js';
import { UnrealBloomPass } from 'three/addons/postprocessing/UnrealBloomPass.js';
import { OutputPass } from 'three/addons/postprocessing/OutputPass.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

export const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
export const BG = 0x000000;

/* ---------- pointer, smoothed once per frame for every scene ---------- */
export const mouse = { x: 0, y: 0, sx: 0, sy: 0 };
addEventListener('pointermove', e => { mouse.x = e.clientX / innerWidth - .5; mouse.y = e.clientY / innerHeight - .5; }, { passive: true });
(function smooth() { mouse.sx += (mouse.x - mouse.sx) * .05; mouse.sy += (mouse.y - mouse.sy) * .05; requestAnimationFrame(smooth); })();

/* ---------- shapes ---------- */
// Nakilab "K" mark, traced from the logo (x right, y down, ~492×376 box)
export const K_POLYS = [
  [[16,14],[72,14],[350,360],[272,360],[16,70]],
  [[16,124],[88,212],[88,318],[50,360],[16,360]],
  [[230,158],[348,14],[444,14],[272,208]],
  [[288,228],[330,180],[482,360],[390,360]],
];
export const kPoint = ([x, y]) => [(x - 249) / 170, -(y - 187) / 170];
export function inPoly(px, py, poly) {
  let inside = false;
  for (let i = 0, j = poly.length - 1; i < poly.length; j = i++) {
    const [xi, yi] = poly[i], [xj, yj] = poly[j];
    if ((yi > py) !== (yj > py) && px < (xj - xi) * (py - yi) / (yj - yi) + xi) inside = !inside;
  }
  return inside;
}
export const fib = (i, n) => { const y = 1 - (i / (n - 1)) * 2, r = Math.sqrt(1 - y * y), t = i * 2.399963; return [Math.cos(t) * r, y, Math.sin(t) * r]; };
// cheap smooth "continents" field on the unit sphere
export const land = (x, y, z) => Math.sin(2.7 * x + 1.3) * Math.sin(2.3 * y + .4) * Math.sin(2.9 * z + 2.1) + .45 * Math.sin(6.1 * x + 3 * z) * Math.sin(5.3 * y + .7);

export function sampleSphere(N, r = 1.35) {
  const a = new Float32Array(N * 3);
  for (let i = 0; i < N; i++) { const [x, y, z] = fib(i, N); const k = r * (land(x, y, z) > .12 ? 1 : .985); a.set([x * k, y * k, z * k], i * 3); }
  return a;
}
export function sampleK(N, scale = 1.05, depth = .3) {
  const a = new Float32Array(N * 3);
  for (let i = 0; i < N;) {
    const px = 16 + Math.random() * 466, py = 14 + Math.random() * 346;
    if (K_POLYS.some(p => inPoly(px, py, p))) { const [x, y] = kPoint([px, py]); a.set([x * scale, y * scale, (Math.random() - .5) * depth], i * 3); i++; }
  }
  return a;
}
export function samplePaw(N) {
  const pads = [[0, -.32, .62, .52], [-.78, .38, .27, .36], [-.3, .9, .27, .36], [.3, .9, .27, .36], [.78, .38, .27, .36]];
  const a = new Float32Array(N * 3);
  for (let i = 0; i < N; i++) {
    const [cx, cy, rx, ry] = pads[i % 5 === 0 ? 0 : (Math.random() < .55 ? 0 : 1 + ((Math.random() * 4) | 0))];
    const t = Math.random() * 6.283, d = Math.sqrt(Math.random());
    a.set([cx + Math.cos(t) * rx * d, cy + Math.sin(t) * ry * d - .15, (Math.random() - .5) * .25], i * 3);
  }
  return a;
}
// a tilted ring of debris — an asteroid belt
export function sampleBelt(N, rIn = 1.1, rOut = 2.1, tilt = 1.15) {
  const a = new Float32Array(N * 3), c = Math.cos(tilt), s = Math.sin(tilt);
  for (let i = 0; i < N; i++) {
    const t = Math.random() * 6.283, r = rIn + Math.pow(Math.random(), .7) * (rOut - rIn), h = (Math.random() - .5) * .08;
    const x = Math.cos(t) * r, z = Math.sin(t) * r;
    a.set([x, h * c - z * s, h * s + z * c], i * 3);
  }
  return a;
}
// loose turbulent cloud — "broken down" matter
export function sampleCloud(N, r = 2.4) {
  const a = new Float32Array(N * 3);
  for (let i = 0; i < N; i++) {
    const v = new THREE.Vector3().randomDirection().multiplyScalar(r * Math.cbrt(Math.random()));
    v.x *= 1.25; v.y *= .8; v.x += Math.sin(v.y * 3) * .3; v.y += Math.cos(v.x * 2.5) * .25;
    a.set([v.x, v.y, v.z], i * 3);
  }
  return a;
}

/* ---------- stage: renderer + bloom, sized to its canvas ---------- */
export function stage(canvas, { fov = 40, z = 6, bloom = [.75, .5, .35], tone = false, env = false } = {}) {
  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, powerPreference: 'high-performance' });
  renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
  renderer.toneMapping = tone ? THREE.ACESFilmicToneMapping : THREE.NoToneMapping;
  const scene = new THREE.Scene();
  scene.background = new THREE.Color(BG);
  if (env) scene.environment = new THREE.PMREMGenerator(renderer).fromScene(new RoomEnvironment(), .04).texture;
  const camera = new THREE.PerspectiveCamera(fov, 1, .1, 100);
  camera.position.set(0, 0, z);
  const composer = new EffectComposer(renderer);
  composer.addPass(new RenderPass(scene, camera));
  const bloomPass = new UnrealBloomPass(new THREE.Vector2(1, 1), ...bloom);
  composer.addPass(bloomPass);
  composer.addPass(new OutputPass());
  const resizers = [];
  let w = 1, h = 1;
  function resize() {
    w = canvas.clientWidth || 1; h = canvas.clientHeight || 1;
    renderer.setSize(w, h, false); composer.setSize(w, h); bloomPass.resolution.set(w, h);
    camera.aspect = w / h; camera.updateProjectionMatrix();
    resizers.forEach(f => f(w, h));
  }
  new ResizeObserver(resize).observe(canvas);
  resize();
  return {
    renderer, scene, camera, composer, bloom: bloomPass,
    onResize(f) { resizers.push(f); f(w, h); },
    render: () => composer.render(),
  };
}

/* run fn(t) + render only while `el` is on screen */
export function animate(el, fn, render) {
  const clock = new THREE.Clock();
  let on = false;
  const loop = () => { if (!on) return; fn(clock.getElapsedTime()); render(); requestAnimationFrame(loop); };
  if (reduce) { fn(0); render(); addEventListener('resize', () => { fn(0); render(); }); return; }
  new IntersectionObserver(([e]) => { on = e.isIntersecting; if (on) requestAnimationFrame(loop); }).observe(el);
}

export function addStars(scene, n = 1500, opacity = .7) {
  const g = new THREE.BufferGeometry(), p = [];
  for (let i = 0; i < n; i++) p.push((Math.random() - .5) * 40, (Math.random() - .5) * 24, -8 - Math.random() * 12);
  g.setAttribute('position', new THREE.Float32BufferAttribute(p, 3));
  const pts = new THREE.Points(g, new THREE.PointsMaterial({ size: .04, color: 0xd6e2ff, transparent: true, opacity }));
  scene.add(pts);
  return pts;
}

/* ---------- morphing particle cloud (up to 4 target shapes) ---------- */
export function morphPoints(targets, colors, renderer) {
  const N = targets[0].length / 3, rnd = new Float32Array(N);
  for (let i = 0; i < N; i++) rnd[i] = Math.random();
  const g = new THREE.BufferGeometry();
  g.setAttribute('position', new THREE.BufferAttribute(targets[0].slice(), 3));
  for (let k = 0; k < 4; k++) g.setAttribute('aT' + k, new THREE.BufferAttribute(targets[Math.min(k, targets.length - 1)], 3));
  g.setAttribute('aR', new THREE.BufferAttribute(rnd, 1));
  const cols = [0, 1, 2, 3].map(k => new THREE.Vector3(...(colors[Math.min(k, colors.length - 1)])));
  const uni = {
    uFrom: { value: 0 }, uTo: { value: 1 }, uT: { value: 0 }, uTime: { value: 0 }, uPx: { value: renderer.getPixelRatio() },
    uGlow: { value: 0 }, uBurst: { value: .22 }, uC: { value: cols },
  };
  const mat = new THREE.ShaderMaterial({
    uniforms: uni, transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
    vertexShader: `
      attribute vec3 aT0; attribute vec3 aT1; attribute vec3 aT2; attribute vec3 aT3; attribute float aR;
      uniform float uFrom, uTo, uT, uTime, uPx, uGlow, uBurst; uniform vec3 uC[4];
      varying vec3 vC; varying float vA;
      vec3 pick(float i){ return i < .5 ? aT0 : (i < 1.5 ? aT1 : (i < 2.5 ? aT2 : aT3)); }
      vec3 col(float i){ return i < .5 ? uC[0] : (i < 1.5 ? uC[1] : (i < 2.5 ? uC[2] : uC[3])); }
      void main(){
        float p = smoothstep(0., 1., clamp(uT * 1.6 - aR * .6, 0., 1.));
        vec3 pos = mix(pick(uFrom), pick(uTo), p);
        float burst = sin(p * 3.14159) * (.6 + aR);
        pos += vec3(sin(aR * 40. + uTime), cos(aR * 33. + uTime * 1.3), sin(aR * 21. + uTime * .7)) * (.01 + burst * uBurst);
        vC = mix(col(uFrom), col(uTo), p) * (1. + uGlow); vA = .55 + aR * .45;
        vec4 mv = modelViewMatrix * vec4(pos, 1.);
        gl_PointSize = (2.2 + aR * 2.4) * (1. + uGlow * .4) * uPx * (3.2 / -mv.z);
        gl_Position = projectionMatrix * mv;
      }`,
    fragmentShader: `varying vec3 vC; varying float vA;
      void main(){ float d = length(gl_PointCoord - .5); if (d > .5) discard; gl_FragColor = vec4(vC, smoothstep(.5, .0, d) * vA); }`,
  });
  return { points: new THREE.Points(g, mat), uni };
}

/* ---------- scroll story helpers ---------- */
export const ease = x => x < .5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2;
export const band = (p, a, b) => ease(Math.min(1, Math.max(0, (p - a) / (b - a))));
export function sectionProgress(sec) {
  const r = sec.getBoundingClientRect();
  return Math.min(1, Math.max(0, -r.top / Math.max(1, r.height - innerHeight)));
}
// mission captions: highlight the active step, reveal the closing line
export function missionCaptions(sec) {
  const steps = [...sec.querySelectorAll('.steps li')], mline = sec.querySelector('.mline'), prog = sec.querySelector('.mprog');
  let last = -1;
  if (reduce) steps.forEach(li => li.classList.add('on'));
  return p => {
    const step = p < .34 ? 0 : p < .68 ? 1 : 2;
    if (!reduce && step !== last) { steps.forEach((li, i) => li.classList.toggle('on', i === step)); last = step; }
    mline.classList.toggle('show', reduce || p > .82);
    prog?.style.setProperty('--p', p.toFixed(3));
  };
}

/* =====================================================================
   HERO SCENES
   ===================================================================== */

/* A · Orbit — dotted planet, orbit rings, data arcs */
export function heroOrbit(canvas) {
  const S = stage(canvas, { z: 6.4, bloom: [.75, .5, .35] });
  const { scene, camera } = S;
  const world = new THREE.Group(); scene.add(world);
  const R = 2.1, BLUE = new THREE.Color(0x7fb2ff), DEEP = new THREE.Color(0x26324a);
  world.add(new THREE.Mesh(new THREE.SphereGeometry(R * .985, 64, 64), new THREE.MeshBasicMaterial({ color: 0x020203 })));
  const N = 14000, pos = new Float32Array(N * 3), col = new Float32Array(N * 3), landPts = [];
  for (let i = 0; i < N; i++) {
    const [x, y, z] = fib(i, N), l = land(x, y, z) > .12;
    pos.set([x * R, y * R, z * R], i * 3);
    const c = l ? BLUE : DEEP; col.set([c.r, c.g, c.b], i * 3);
    if (l) landPts.push([x, y, z]);
  }
  const g = new THREE.BufferGeometry();
  g.setAttribute('position', new THREE.BufferAttribute(pos, 3));
  g.setAttribute('color', new THREE.BufferAttribute(col, 3));
  world.add(new THREE.Points(g, new THREE.PointsMaterial({ size: .022, vertexColors: true, transparent: true, opacity: 1 })));
  const cityG = new THREE.BufferGeometry(), cp = [];
  for (let i = 0; i < 140; i++) { const p = landPts[(Math.random() * landPts.length) | 0]; cp.push(p[0] * R * 1.003, p[1] * R * 1.003, p[2] * R * 1.003); }
  cityG.setAttribute('position', new THREE.Float32BufferAttribute(cp, 3));
  const cityM = new THREE.PointsMaterial({ size: .05, color: 0xe6eeff, transparent: true });
  world.add(new THREE.Points(cityG, cityM));
  const atmo = new THREE.Mesh(new THREE.SphereGeometry(R * 1.16, 64, 64), new THREE.ShaderMaterial({
    side: THREE.BackSide, blending: THREE.AdditiveBlending, transparent: true, depthWrite: false,
    vertexShader: 'varying vec3 n; void main(){ n = normalize(normalMatrix*normal); gl_Position = projectionMatrix*modelViewMatrix*vec4(position,1.); }',
    fragmentShader: 'varying vec3 n; void main(){ float i = pow(clamp(-dot(n, vec3(0.,0.,1.)), 0., 1.), 2.5) * .9; gl_FragColor = vec4(.3,.55,1.,1.)*i; }',
  }));
  const arcs = [], arcMat = new THREE.LineBasicMaterial({ color: 0xa8c8ff, transparent: true, blending: THREE.AdditiveBlending });
  function newArc(a) {
    const p1 = new THREE.Vector3(...landPts[(Math.random() * landPts.length) | 0]).multiplyScalar(R);
    const p2 = new THREE.Vector3(...landPts[(Math.random() * landPts.length) | 0]).multiplyScalar(R);
    const mid = p1.clone().add(p2).normalize().multiplyScalar(R * (1 + p1.distanceTo(p2) * .22));
    a.line.geometry.setFromPoints(new THREE.QuadraticBezierCurve3(p1, mid, p2).getPoints(60));
    a.t = -Math.random() * 2; a.speed = .35 + Math.random() * .3;
  }
  for (let i = 0; i < 9; i++) { const a = { line: new THREE.Line(new THREE.BufferGeometry(), arcMat) }; newArc(a); world.add(a.line); arcs.push(a); }
  const orbits = new THREE.Group(); scene.add(orbits); orbits.add(atmo);
  const rings = [[2.7, .32, .3, .5], [3.3, -.28, -.32, .32], [3.9, .18, .2, .22]].map(([r, rx, rz, speed], i) => {
    const ring = new THREE.Group(); ring.rotation.set(Math.PI / 2 + rx, 0, rz);
    ring.add(new THREE.Mesh(new THREE.TorusGeometry(r, .0045, 8, 240), new THREE.MeshBasicMaterial({ color: 0x9cc2ff, transparent: true, opacity: .85 - i * .15, blending: THREE.AdditiveBlending })));
    const sat = new THREE.Mesh(new THREE.SphereGeometry(.035, 16, 16), new THREE.MeshBasicMaterial({ color: 0xffffff }));
    ring.add(sat); orbits.add(ring);
    return { sat, r, speed, off: i * 2.1 };
  });
  const stars = addStars(scene);
  S.onResize(w => {
    const mob = w < 900;
    world.position.y = orbits.position.y = mob ? -3.0 : -2.9;
    camera.position.z = mob ? 9 : 6.4;
  });
  if (reduce) arcs.forEach(a => { a.t = .7; });
  animate(canvas.parentElement, t => {
    const m = mouse;
    world.rotation.y = t * .06 + m.sx * .5; world.rotation.x = .25 + m.sy * .15;
    orbits.rotation.x = m.sy * .12; orbits.rotation.y = m.sx * .25;
    rings.forEach(o => { const a = t * o.speed + o.off; o.sat.position.set(Math.cos(a) * o.r, Math.sin(a) * o.r, 0); });
    arcs.forEach(a => {
      a.t += reduce ? 0 : .016 * a.speed;
      const n = 61, head = Math.floor(a.t * n), tail = 22;
      a.line.geometry.setDrawRange(Math.max(0, head - tail), Math.max(0, Math.min(head, n) - Math.max(0, head - tail)));
      if (a.t > 1.5) newArc(a);
    });
    cityM.opacity = .75 + .25 * Math.sin(t * 2.3);
    stars.position.x = -m.sx * .6; stars.position.y = m.sy * .4;
  }, S.render);
}

/* the K as a beveled metal extrusion */
export function kMesh({ color = 0x2b2e35, depth = .42 } = {}) {
  const shapes = K_POLYS.map(poly => new THREE.Shape(poly.map(p => new THREE.Vector2(...kPoint(p)))));
  const geo = new THREE.ExtrudeGeometry(shapes, { depth, bevelEnabled: true, bevelThickness: .05, bevelSize: .035, bevelSegments: 4, curveSegments: 1 });
  geo.center();
  const mat = new THREE.MeshPhysicalMaterial({ color, metalness: 1, roughness: .26, clearcoat: 1, clearcoatRoughness: .15, envMapIntensity: 1.4 });
  const group = new THREE.Group();
  group.add(new THREE.Mesh(geo, mat));
  group.add(new THREE.LineSegments(new THREE.EdgesGeometry(geo, 25), new THREE.LineBasicMaterial({ color: 0x8fbaff, transparent: true, opacity: .55, blending: THREE.AdditiveBlending })));
  return group;
}

/* B · Monolith — the K in 3D before a glowing portal */
export function heroMonolith(canvas) {
  const S = stage(canvas, { z: 6, bloom: [.75, .5, .35], tone: true, env: true });
  const { scene, camera } = S;
  scene.fog = new THREE.Fog(BG, 7, 18);
  const group = kMesh(); scene.add(group);
  scene.add(new THREE.AmbientLight(0x444a55, .4));
  const rim = new THREE.DirectionalLight(0x6fa3ff, 3); rim.position.set(-4, 3, -3); scene.add(rim);
  const key = new THREE.PointLight(0xbcd6ff, 30, 12); scene.add(key);
  const portal = portalRing(); scene.add(portal.group);
  const grid = new THREE.GridHelper(60, 90, 0x2c3a55, 0x15171d);
  grid.position.y = -1.75; grid.material.transparent = true; grid.material.opacity = .5; scene.add(grid);
  const dust = risingDust(scene);
  let L = { x: 1.75, y: 0 };
  S.onResize(w => {
    const mob = w < 900;
    L = { x: mob ? 0 : 1.75, y: mob ? 1.4 : 0 };
    group.position.x = L.x; group.scale.setScalar(mob ? .55 : .78);
    portal.group.position.set(L.x, L.y, -1.4); portal.group.scale.setScalar(mob ? .55 : .8);
    camera.position.z = mob ? 8 : 6;
  });
  animate(canvas.parentElement, t => {
    const m = mouse;
    group.rotation.y = Math.sin(t * .4) * .35 + m.sx * .7;
    group.rotation.x = Math.sin(t * .3) * .06 + m.sy * .3;
    group.position.y = L.y + Math.sin(t * .8) * .08;
    key.position.set(Math.cos(t * .6) * 3 + L.x, 1.2, Math.sin(t * .6) * 3 + 1);
    portal.tick(t); dust(t);
    camera.position.x = m.sx * .5; camera.position.y = .2 - m.sy * .3;
    camera.lookAt(0, 0, 0);
  }, S.render);
}
export function portalRing() {
  const group = new THREE.Group();
  const ring1 = new THREE.Mesh(new THREE.TorusGeometry(2.05, .014, 16, 220), new THREE.MeshBasicMaterial({ color: 0xc8dcff }));
  const ring2 = new THREE.Mesh(new THREE.TorusGeometry(2.4, .006, 16, 220), new THREE.MeshBasicMaterial({ color: 0x5b8fff, transparent: true, opacity: .6 }));
  const disc = new THREE.Mesh(new THREE.CircleGeometry(2.05, 96), new THREE.ShaderMaterial({
    transparent: true, blending: THREE.AdditiveBlending, depthWrite: false,
    vertexShader: 'varying vec2 u; void main(){ u = uv; gl_Position = projectionMatrix*modelViewMatrix*vec4(position,1.); }',
    fragmentShader: 'varying vec2 u; void main(){ float d = distance(u, vec2(.5)); float a = smoothstep(.5,.0,d)*.14 + smoothstep(.4,.5,d)*smoothstep(.5,.47,d)*.35; gl_FragColor = vec4(.3,.55,1.,a); }',
  }));
  group.add(disc, ring1, ring2);
  return { group, tick: t => { ring2.rotation.y = t * .3; ring2.rotation.x = Math.sin(t * .2) * .3; ring1.scale.setScalar(1 + Math.sin(t * 1.2) * .01); } };
}
export function risingDust(scene, n = 900) {
  const dp = new Float32Array(n * 3);
  for (let i = 0; i < n; i++) dp.set([(Math.random() - .5) * 12, Math.random() * 6 - 2, (Math.random() - .5) * 8 - 1], i * 3);
  const dg = new THREE.BufferGeometry(); dg.setAttribute('position', new THREE.BufferAttribute(dp, 3));
  scene.add(new THREE.Points(dg, new THREE.PointsMaterial({ size: .025, color: 0xb4ccff, transparent: true, opacity: .7 })));
  return () => {
    if (reduce) return;
    const a = dg.attributes.position.array;
    for (let i = 0; i < n; i++) { a[i * 3 + 1] += .003; if (a[i * 3 + 1] > 4) a[i * 3 + 1] = -2; }
    dg.attributes.position.needsUpdate = true;
  };
}

/* C · Particles — world → K → paw, morphing on a timer */
export function heroParticles(canvas) {
  const S = stage(canvas, { z: 6, bloom: [.7, .5, .35] });
  const { scene, camera, renderer } = S;
  const N = 16000;
  const m = morphPoints([sampleSphere(N), sampleK(N), samplePaw(N)], [[.45, .65, 1], [.85, .9, 1], [1, .55, .25]], renderer);
  const group = new THREE.Group(); group.add(m.points); scene.add(group);
  const halo = new THREE.Mesh(new THREE.RingGeometry(1.9, 1.91, 160), new THREE.MeshBasicMaterial({ color: 0x5b8fff, transparent: true, opacity: .5, side: THREE.DoubleSide }));
  group.add(halo);
  const stars = addStars(scene, 1400, .6);
  S.onResize(w => {
    const mob = w < 900;
    group.position.set(mob ? 0 : 1.75, mob ? 1.15 : 0, 0);
    group.scale.setScalar(mob ? .55 : .74);
    camera.position.z = mob ? 8 : 6;
  });
  const HOLD = 3.2, MORPH = 2.2;
  animate(canvas.parentElement, t => {
    const cyc = HOLD + MORPH, k = Math.floor(t / cyc), local = t - k * cyc;
    m.uni.uFrom.value = k % 3; m.uni.uTo.value = (k + 1) % 3;
    m.uni.uT.value = Math.max(0, (local - HOLD) / MORPH);
    m.uni.uTime.value = t;
    group.rotation.y = Math.sin(t * .35) * .45 + mouse.sx * .8;
    group.rotation.x = mouse.sy * .4;
    halo.rotation.z = t * .1; halo.rotation.x = 1.2 + Math.sin(t * .4) * .1;
    stars.position.x = -mouse.sx * .6;
  }, S.render);
}

/* =====================================================================
   MISSION SCENES (scroll-driven: break it down → rebuild it better → ship it)
   ===================================================================== */

function missionLayout(S, group, desk = { x: 2.6, y: 0, s: .7 }, mob = { x: 0, y: 1.45, s: .55 }) {
  const L = { x: 0, y: 0, s: 1 };
  S.onResize(w => {
    Object.assign(L, w < 900 ? mob : desk);
    S.camera.position.z = w < 900 ? 9 : 7.5;
    group.scale.setScalar(L.s);
  });
  return L;
}

/* B · voxel block breaks apart, rebuilds into the K, then ships */
export function missionVoxels(sec, canvas) {
  const S = stage(canvas, { fov: 38, z: 7.5, bloom: [.5, .45, .7], tone: true, env: true });
  const { scene } = S, caption = missionCaptions(sec);
  const STEP = 21, K = [];
  for (let y = 14 + STEP / 2; y < 360; y += STEP) for (let x = 16 + STEP / 2; x < 482; x += STEP)
    if (K_POLYS.some(pl => inPoly(x, y, pl))) { const [kx, ky] = kPoint([x, y]); K.push([kx, ky, -.065], [kx, ky, .065]); }
  const N = K.length, kSize = STEP / 170 * .9, side = Math.ceil(Math.cbrt(N)), sp = 1.9 / side;
  const cube = [], scatter = [], axis = [];
  for (let i = 0; i < N; i++) {
    const cx = i % side, cy = Math.floor(i / side) % side, cz = Math.floor(i / side / side);
    cube.push([(cx - (side - 1) / 2) * sp, (cy - (side - 1) / 2) * sp, (cz - (side - 1) / 2) * sp]);
    const d = new THREE.Vector3().randomDirection().multiplyScalar(2.2 + Math.random() * 2.4);
    scatter.push([d.x * .9 + .3, d.y * .75, d.z * .7]);
    axis.push(new THREE.Vector3().randomDirection());
  }
  for (let i = N - 1; i > 0; i--) { const j = (Math.random() * (i + 1)) | 0; [K[i], K[j]] = [K[j], K[i]]; }
  const mat = new THREE.MeshPhysicalMaterial({ color: 0x2b2e35, metalness: .95, roughness: .3, clearcoat: 1, clearcoatRoughness: .2, emissive: 0x3b7bff, emissiveIntensity: 0 });
  const mesh = new THREE.InstancedMesh(new THREE.BoxGeometry(1, 1, 1), mat, N);
  const group = new THREE.Group(); group.add(mesh); scene.add(group);
  scene.add(new THREE.AmbientLight(0x444a55, .5));
  const rim = new THREE.DirectionalLight(0x7fb2ff, 3); rim.position.set(-3, 4, -2); scene.add(rim);
  const key = new THREE.PointLight(0xd6e2ff, 25, 14); key.position.set(3, 2, 4); scene.add(key);
  const ring = new THREE.Mesh(new THREE.TorusGeometry(1.9, .012, 12, 200), new THREE.MeshBasicMaterial({ color: 0xc8dcff, transparent: true, opacity: 0 }));
  ring.rotation.x = Math.PI / 2.3; group.add(ring);
  addStars(scene, 700, .5);
  const L = missionLayout(S, group);
  const dummy = new THREE.Object3D(), q = new THREE.Quaternion();
  animate(sec, t => {
    const p = reduce ? 1 : sectionProgress(sec);
    const a = band(p, .02, .3), b = band(p, .36, .66), c = band(p, .7, .9);
    for (let i = 0; i < N; i++) {
      const [x0, y0, z0] = cube[i], [x1, y1, z1] = scatter[i], [x2, y2, z2] = K[i];
      const fx = x0 + (x1 - x0) * a, fy = y0 + (y1 - y0) * a, fz = z0 + (z1 - z0) * a;
      const fl = a - b > 0 ? Math.sin(t * .8 + i) * .06 * (a - b) : 0;
      dummy.position.set(fx + (x2 - fx) * b, fy + (y2 - fy) * b + fl, fz + (z2 - fz) * b);
      const sc = sp * .94 + (kSize * .8 - sp * .94) * a; dummy.scale.setScalar(sc + (kSize - sc) * b);
      q.setFromAxisAngle(axis[i], a * (1 - b) * Math.PI * 1.5 + (a - b > 0 ? t * .3 : 0)); dummy.quaternion.copy(q);
      dummy.updateMatrix(); mesh.setMatrixAt(i, dummy.matrix);
    }
    mesh.instanceMatrix.needsUpdate = true;
    mat.emissiveIntensity = c * .7;
    ring.material.opacity = c * .9; ring.scale.setScalar(.8 + c * .4);
    S.bloom.strength = .45 + c * .35;
    group.position.set(L.x, L.y + c * .12, 0);
    group.rotation.y = (1 - b) * (t * .25 + .6) + b * Math.sin(t * .5) * .3 + mouse.sx * .5;
    group.rotation.x = (1 - b) * .45 + mouse.sy * .25;
    caption(p);
  }, S.render);
}

/* A · a planet dissolves into an orbiting belt, the belt condenses into the K, satellites launch */
export function missionOrbit(sec, canvas) {
  const S = stage(canvas, { fov: 38, z: 7.5, bloom: [.7, .5, .3] });
  const { scene, renderer } = S, caption = missionCaptions(sec);
  const N = 14000;
  const m = morphPoints([sampleSphere(N, 1.25), sampleBelt(N, .85, 1.65), sampleK(N)], [[.45, .65, 1], [.7, .8, 1], [.85, .92, 1]], renderer);
  m.uni.uBurst.value = .12;
  const group = new THREE.Group(); group.add(m.points); scene.add(group);
  const sats = [0, 1, 2].map(i => {
    const ring = new THREE.Group(); ring.rotation.set(Math.PI / 2 + (i - 1) * .35, 0, (i - 1) * .3);
    const r = 1.75 + i * .3;
    const line = new THREE.Mesh(new THREE.TorusGeometry(r, .004, 8, 200), new THREE.MeshBasicMaterial({ color: 0x9cc2ff, transparent: true, opacity: 0, blending: THREE.AdditiveBlending }));
    const dot = new THREE.Mesh(new THREE.SphereGeometry(.04, 12, 12), new THREE.MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0 }));
    ring.add(line, dot); group.add(ring);
    return { line, dot, r, off: i * 2.1, speed: .6 - i * .12 };
  });
  addStars(scene, 900, .55);
  const L = missionLayout(S, group);
  animate(sec, t => {
    const p = reduce ? 1 : sectionProgress(sec);
    if (p < .34) { m.uni.uFrom.value = 0; m.uni.uTo.value = 1; m.uni.uT.value = band(p, .03, .3); }
    else { m.uni.uFrom.value = 1; m.uni.uTo.value = 2; m.uni.uT.value = band(p, .38, .66); }
    const c = band(p, .7, .9);
    m.uni.uTime.value = t; m.uni.uGlow.value = c * .6;
    sats.forEach(s => { const a = t * s.speed + s.off; s.dot.position.set(Math.cos(a) * s.r, Math.sin(a) * s.r, 0); s.line.material.opacity = c * .7; s.dot.material.opacity = c; });
    group.position.set(L.x, L.y, 0);
    group.rotation.y = Math.sin(t * .3) * .3 + mouse.sx * .5 + (1 - band(p, .38, .66)) * t * .15;
    group.rotation.x = mouse.sy * .25;
    caption(p);
  }, S.render);
}

/* C · a sphere of light bursts into chaos, the chaos reassembles into the K, which then shines */
export function missionParticles(sec, canvas) {
  const S = stage(canvas, { fov: 38, z: 7.5, bloom: [.7, .5, .3] });
  const { scene, renderer } = S, caption = missionCaptions(sec);
  const N = 16000;
  const m = morphPoints([sampleSphere(N, 1.25), sampleCloud(N, 1.6), sampleK(N)], [[.45, .65, 1], [.55, .6, .95], [.9, .93, 1]], renderer);
  m.uni.uBurst.value = .3;
  const group = new THREE.Group(); group.add(m.points); scene.add(group);
  const shock = new THREE.Mesh(new THREE.RingGeometry(1.6, 1.62, 160), new THREE.MeshBasicMaterial({ color: 0xc8dcff, transparent: true, opacity: 0, side: THREE.DoubleSide }));
  group.add(shock);
  addStars(scene, 900, .55);
  const L = missionLayout(S, group);
  animate(sec, t => {
    const p = reduce ? 1 : sectionProgress(sec);
    if (p < .34) { m.uni.uFrom.value = 0; m.uni.uTo.value = 1; m.uni.uT.value = band(p, .03, .3); }
    else { m.uni.uFrom.value = 1; m.uni.uTo.value = 2; m.uni.uT.value = band(p, .38, .66); }
    const c = band(p, .7, .9);
    m.uni.uTime.value = t; m.uni.uGlow.value = c * .8;
    const wave = (t * .5) % 1;
    shock.scale.setScalar(1 + wave * 1.2); shock.material.opacity = c * (1 - wave) * .8;
    group.position.set(L.x, L.y, 0);
    group.rotation.y = Math.sin(t * .35) * .35 + mouse.sx * .6;
    group.rotation.x = mouse.sy * .3;
    caption(p);
  }, S.render);
}
