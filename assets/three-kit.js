// Nakilab — three.js toolkit: stage setup, the K shape, and the Monolith hero + mission scenes.
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

// a tilted ring of debris — an asteroid belt
// loose turbulent cloud — "broken down" matter

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
  const pts = new THREE.Points(g, new THREE.PointsMaterial({ size: .04, color: 0xe4e4e7, transparent: true, opacity }));
  scene.add(pts);
  return pts;
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

/* the K as a beveled metal extrusion */
export function kMesh({ color = 0x2b2e35, depth = .42 } = {}) {
  const shapes = K_POLYS.map(poly => new THREE.Shape(poly.map(p => new THREE.Vector2(...kPoint(p)))));
  const geo = new THREE.ExtrudeGeometry(shapes, { depth, bevelEnabled: true, bevelThickness: .05, bevelSize: .035, bevelSegments: 4, curveSegments: 1 });
  geo.center();
  const mat = new THREE.MeshPhysicalMaterial({ color, metalness: 1, roughness: .26, clearcoat: 1, clearcoatRoughness: .15, envMapIntensity: 1.4 });
  const group = new THREE.Group();
  group.add(new THREE.Mesh(geo, mat));
  group.add(new THREE.LineSegments(new THREE.EdgesGeometry(geo, 25), new THREE.LineBasicMaterial({ color: 0xffffff, transparent: true, opacity: .55, blending: THREE.AdditiveBlending })));
  return group;
}

/* B · Monolith — the K in 3D before a glowing portal */
export function heroMonolith(canvas) {
  const S = stage(canvas, { z: 6, bloom: [.75, .5, .35], tone: true, env: true });
  const { scene, camera } = S;
  scene.fog = new THREE.Fog(BG, 7, 18);
  const group = kMesh(); scene.add(group);
  scene.add(new THREE.AmbientLight(0x45454a, .4));
  const rim = new THREE.DirectionalLight(0xffffff, 3); rim.position.set(-4, 3, -3); scene.add(rim);
  const key = new THREE.PointLight(0xffffff, 30, 12); scene.add(key);
  const portal = portalRing(); scene.add(portal.group);
  const grid = new THREE.GridHelper(60, 90, 0x3f3f46, 0x18181b);
  grid.position.y = -1.75; grid.material.transparent = true; grid.material.opacity = .5; scene.add(grid);
  const dust = risingDust(scene);
  let L = { x: 1.75, y: 0 };
  S.onResize(w => {
    const mob = w < 900;
    L = { x: mob ? 0 : 2.25, y: mob ? 1.4 : .15 };
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
  const ring1 = new THREE.Mesh(new THREE.TorusGeometry(2.05, .014, 16, 220), new THREE.MeshBasicMaterial({ color: 0xffffff }));
  const ring2 = new THREE.Mesh(new THREE.TorusGeometry(2.4, .006, 16, 220), new THREE.MeshBasicMaterial({ color: 0xa1a1aa, transparent: true, opacity: .6 }));
  const disc = new THREE.Mesh(new THREE.CircleGeometry(2.05, 96), new THREE.ShaderMaterial({
    transparent: true, blending: THREE.AdditiveBlending, depthWrite: false,
    vertexShader: 'varying vec2 u; void main(){ u = uv; gl_Position = projectionMatrix*modelViewMatrix*vec4(position,1.); }',
    fragmentShader: 'varying vec2 u; void main(){ float d = distance(u, vec2(.5)); float a = smoothstep(.5,.0,d)*.14 + smoothstep(.4,.5,d)*smoothstep(.5,.47,d)*.35; gl_FragColor = vec4(1.,1.,1.,a*.55); }',
  }));
  group.add(disc, ring1, ring2);
  return { group, tick: t => { ring2.rotation.y = t * .3; ring2.rotation.x = Math.sin(t * .2) * .3; ring1.scale.setScalar(1 + Math.sin(t * 1.2) * .01); } };
}
export function risingDust(scene, n = 900) {
  const dp = new Float32Array(n * 3);
  for (let i = 0; i < n; i++) dp.set([(Math.random() - .5) * 12, Math.random() * 6 - 2, (Math.random() - .5) * 8 - 1], i * 3);
  const dg = new THREE.BufferGeometry(); dg.setAttribute('position', new THREE.BufferAttribute(dp, 3));
  scene.add(new THREE.Points(dg, new THREE.PointsMaterial({ size: .025, color: 0xa1a1aa, transparent: true, opacity: .7 })));
  return () => {
    if (reduce) return;
    const a = dg.attributes.position.array;
    for (let i = 0; i < n; i++) { a[i * 3 + 1] += .003; if (a[i * 3 + 1] > 4) a[i * 3 + 1] = -2; }
    dg.attributes.position.needsUpdate = true;
  };
}

/* =====================================================================
   MISSION SCENES (scroll-driven: break it down → rebuild it better → ship it)
   ===================================================================== */

function missionLayout(S, group, desk = { x: 2.9, y: 0, s: .66 }, mob = { x: 0, y: 1.45, s: .55 }) {
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
  const mat = new THREE.MeshPhysicalMaterial({ color: 0x2b2e35, metalness: .95, roughness: .3, clearcoat: 1, clearcoatRoughness: .2, emissive: 0xffffff, emissiveIntensity: 0 });
  const mesh = new THREE.InstancedMesh(new THREE.BoxGeometry(1, 1, 1), mat, N);
  const group = new THREE.Group(); group.add(mesh); scene.add(group);
  scene.add(new THREE.AmbientLight(0x45454a, .5));
  const rim = new THREE.DirectionalLight(0xffffff, 3); rim.position.set(-3, 4, -2); scene.add(rim);
  const key = new THREE.PointLight(0xe4e4e7, 25, 14); key.position.set(3, 2, 4); scene.add(key);
  const ring = new THREE.Mesh(new THREE.TorusGeometry(1.9, .012, 12, 200), new THREE.MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0 }));
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

