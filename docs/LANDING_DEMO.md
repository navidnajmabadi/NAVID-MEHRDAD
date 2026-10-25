# Landing demo video — how to replace

The marketing page (`index.php`) shows a **Sipo HTML/CSS motion demo** until a real MP4 is present.

## Drop-in file

1. Export your cut as **H.264 MP4**, ~16:9, under ~15 MB preferred.
2. Upload/commit as:

```text
assets/video/sipaw-demo.mp4
```

3. Optional: replace the poster frame:

```text
assets/img/landing/demo-poster.png
```

SVG source / store copies also live at `assets/img/landing/demo-poster.svg` and the agent media folder `media/landing/`.

4. Deploy (merge to `PetFinder` → Hostinger pull). Hard-refresh https://sipaw.nakilab.com/

When `assets/video/sipaw-demo.mp4` is readable, `index.php` automatically switches from the motion placeholder to a native `<video controls poster="…">` player. No PHP edit required.

## Placeholder behaviour

| Asset | Role |
|-------|------|
| (missing) `sipaw-demo.mp4` | CSS/Sipo chat motion demo on the page |
| `demo-poster.png` | `<video poster>` + brand still |
| `demo-poster-sipo.webp` | Optional Sipo still (Ice Blue idle) |

## Do not

- Commit secrets or Hostinger `sipaw.php`.
- Put large raw camera dumps in git — host on CDN and change the `<source>` only if needed.
