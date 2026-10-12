# Nakilab — nakilab.com

Startup studio site: "We turn ideas into companies", powered by SI — Super Intelligence.

Static HTML + one small PHP endpoint. No build step.

- `index.html` — the one-page site (SpaceX-style full-screen panels)
- `assets/studio.css`, `assets/studio.js` — styles and page behaviour (intake form)
- `assets/three-kit.js` — three.js scenes: the 3D K hero and the scroll "method" story
- `api/intake.php` — stores intake submissions in a private SQLite file outside the
  web root (`domains/nakilab.com/intake-data/intake.sqlite` on Hostinger)

To add a project, copy an `<article class="project">` block in the Projects section.

Preview locally (PHP needed for the form):

```bash
NAKILAB_INTAKE_DIR=/tmp/nakilab-intake php -S localhost:8090
```

Other branches in this repo:
- `PetFinder` — RingRingPaw (QR pet tag), deployed to ringringpaw.nakilab.com
