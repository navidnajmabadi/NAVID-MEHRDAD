# SIPaw Mascot: themable colours

The mascot comes in six expressions: `idle`, `thinking`, `writing`, `listening`, `happy` and `alert`.
Every colour belongs to a named **role**. In the themable SVGs, each role is a CSS custom property
with a hex fallback. If you set nothing, you get the **Ice Blue** primary look.

```
fill: var(--sipaw-body, #EEF3FF)
```

Brand source: SI Blue `#2E6AFF` and the neutrals come from the SIPaw Brandbook v1.0.
The other blues (`#EEF3FF`, `#DCE6FF`, `#B4C9FF`, `#82A6FF`, `#5C8BFF`, `#1A47C2`, `#0B1B4D`) are derived from SI Blue.

## Files

| File | What it is |
|---|---|
| `svg/sipaw-<expression>.svg` | Themable SVGs. Colours are inline `style="fill:var(--sipaw-…, #hex)"` |
| `themes.json` | Role descriptions plus the presets `ice-blue` (primary), `blue-body`, `mono` and `dark` |
| `sipaw-mascot-themer.html` | Self-contained colour picker. Open it in a browser, choose a preset or set any role, then download baked SVG or PNG, themable SVG, the theme JSON, or the CSS |
| `../tools/build.py` | Bakes static SVG and PNG (plus GIF, WebP, APNG and WebM animations) for any theme |

## Roles

| Role | Used for | Ice Blue | Blue Body | Mono | Dark |
|---|---|---|---|---|---|
| `body` | Main body fill | #EEF3FF | #2E6AFF | #FFFFFF | #18181B |
| `shade` | Body shading | #B4C9FF | #1A47C2 | #E4E4E7 | #0A0A0B |
| `seam` | Body seam line | #82A6FF | #1A47C2 | #A1A1AA | #3F3F46 |
| `bezel` | Ring around the screen | #DCE6FF | #82A6FF | #E4E4E7 | #3F3F46 |
| `bezel-line` | Edge of the screen ring | #82A6FF | #0B1B4D | #A1A1AA | #71717A |
| `screen` | Screen face | #0B1B4D | #0B1B4D | #0A0A0B | #000000 |
| `line` | Outlines | #0B1B4D | #0B1B4D | #18181B | #71717A |
| `tip` | Antenna tops | #2E6AFF | #FFFFFF | #FFFFFF | #2E6AFF |
| `tip-hi` | Antenna highlight | #82A6FF | #DCE6FF | #E4E4E7 | #82A6FF |
| `base` | Antenna bases | #EEF3FF | #2E6AFF | #FFFFFF | #18181B |
| `ring` | Antenna ring joints | #1A47C2 | #B4C9FF | #A1A1AA | #2E6AFF |
| `arm` | Arms and hands | #2E6AFF | #2E6AFF | #FFFFFF | #3F3F46 |
| `arm-shade` | Arm shading | #1A47C2 | #1A47C2 | #E4E4E7 | #18181B |
| `accent` | Effects outside the body: bubbles, sparkles, sound arcs, alert marks | #2E6AFF | #2E6AFF | #71717A | #2E6AFF |
| `on-screen` | Sound bars, typing line, thinking dots | #5C8BFF | #82A6FF | #A1A1AA | #5C8BFF |
| `dim` | Dimmest thinking dot | #1A47C2 | #1A47C2 | #3F3F46 | #1A47C2 |
| `pencil` | Pencil body | #2E6AFF | #FFFFFF | #FFFFFF | #2E6AFF |
| `eraser` | Pencil eraser band | #FFFFFF | #2E6AFF | #A1A1AA | #FFFFFF |
| `glyph` | Eyes, mouth, cursor | #FFFFFF | #FFFFFF | #FFFFFF | #FFFFFF |

## Web (plain HTML/CSS)

CSS variables only reach the SVG when it is **inline** in the DOM. In `<img src>`, `background-image` or
`<object>`, the SVG renders with its fallback colours (Ice Blue). For other colours there, bake files
with the themer or `build.py`.

You only need to set the roles you want to change. Every other role keeps its fallback.
To switch presets at runtime, load `themes.json` and set each `--sipaw-<role>` on a wrapper.

## Animations

The ready-made animations for Ice Blue and Blue Body are in `../animated/<look>/`.

- **WebP and APNG** have full alpha and are the best choice for the web:
  `<picture><source srcset="x.webp" type="image/webp"><img src="x.gif"></picture>`.
- **GIF** has 1-bit transparency. Edges are thresholded, not matted, so it works on light and dark pages but looks slightly jagged next to WebP.
