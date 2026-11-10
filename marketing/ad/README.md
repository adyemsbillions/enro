# Enro ad (1920×1080, ~82 s)

Built as a web page and rendered frame by frame in headless Chrome, so every frame is exact.
The phone footage is **real screenshots of the Enro app** (in `shots/`), filmed from the app's web build with a demo account.

## Files

| File | What it is |
|---|---|
| `ad.html` | The whole film: scenes, text, timings (search for `the film`) |
| `audio.html` | Original soundtrack + sound effects (synthesised, no licensing issues) |
| `shots/` | Real app screens used on the phone and floating cards |
| `render.mjs` | Renders frames and encodes the MP4 (H.264 + AAC) |
| `make-audio.mjs` | Renders the soundtrack to `audio.f32` (+ `soundtrack.wav` to listen to) |
| `verify.mjs`, `sheet.mjs` | Check a rendered video / make contact sheets of stills |

Needs Node 20+ and Google Chrome. Run `npm install` once, in this folder.

## Re-render

```bash
node make-audio.mjs                      # only after changing audio.html
node render.mjs stills 3,23.6,41.8       # quick look at a few moments → stills/
node render.mjs video ../enro-ad.mp4     # full video (~9 min)
node render.mjs video test.mp4 30 40     # just seconds 30–40
```

## Editing

- **Change wording:** edit the text in the scene's `<section>` in `ad.html`. If the text gets longer, check it with `stills`.
- **Change timing:** in `ad.html`, each scene has `scene('sN', start, end)` plus cue times. If you move a cue, move the matching sound cue in `audio.html` (`CUES`) as well.
- **Use new app screens:** replace the PNGs in `shots/`. They need to be 1170×2532 phone screenshots. The crop boxes (`data-crop`) and tap points (`tap(...)`) are in those pixels.

No store badges or download links appear anywhere, by design.
