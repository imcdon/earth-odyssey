# Raw media

Drop original photos here. This folder's images are gitignored; `_raw/README.md` stays tracked.

## Expected structure

```
_raw/
  images/
    hero/         # day-hike, autumn-camp, weather, services, header-bg
    about/        # hero.webp = banner; editor.webp = editor panel (separate files + copy)
    categories/   # hiking, camping, fishing, hunting, weather,
                  # gallery-landscapes, gallery-wildlife, gallery-campsite, gallery-water, alaska
    galleries/
      alaska/     # photos shown in the Alaska gallery
```

## Processing

Raw images may use mixed extensions (JPEG, PNG, WebP, HEIC/HEIF, etc.). The script normalizes collisions (e.g. `day-hike.JPG` + `day-hike.heic`), picks one source file, and emits a single **lowercase `.webp`** per logical name under `assets/img/`.

Each WebP also gets a sibling **`.json` sidecar** with capture metadata when EXIF is present:

```json
{ "make": "Apple", "model": "iPhone 17 Pro Max", "datetime_original": "2026-01-02T10:45:38" }
```

```bash
# Process all images (resize to 2400px wide, WebP quality 82) + write EXIF sidecars
npm install
npm run media:images

# Sidecars only (no re-encode) — useful after dropping raw files for existing WebPs
npm run media:meta
```

Outputs go to `assets/img/...` matching the paths referenced from `includes/config.php`, seed data, and page templates.

Keep `assets/img/logo.svg` and `favicon.svg` unless you add replacements under `_raw/images/` with those stems (processed next to them as `.webp` — you would still need to point the header at the new files).
