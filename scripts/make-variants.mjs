#!/usr/bin/env node
// Generate responsive width variants for site images: name.webp -> name-480.webp, name-960.webp, name-1600.webp.
// Run `npm run media:variants` after adding or replacing photos. Safe to re-run; up-to-date variants are skipped.

import { readdir, stat } from "node:fs/promises";
import path from "node:path";
import sharp from "sharp";

const ROOT = path.resolve(process.cwd(), "assets/img");
const WIDTHS = [480, 960, 1600];
const QUALITY = 78;
const SKIP_DIRS = new Set(["drive-download-20260714T021053Z-1-001"]);
const VARIANT_RE = /-\d+\.webp$/i;

async function walk(dir) {
  const out = [];
  for (const entry of await readdir(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (!SKIP_DIRS.has(entry.name)) out.push(...(await walk(full)));
    } else if (entry.name.toLowerCase().endsWith(".webp") && !VARIANT_RE.test(entry.name)) {
      out.push(full);
    }
  }
  return out;
}

async function isFresh(target, sourceMtime) {
  try {
    return (await stat(target)).mtimeMs >= sourceMtime;
  } catch {
    return false;
  }
}

let made = 0;
for (const file of await walk(ROOT)) {
  const { width } = await sharp(file).metadata();
  const sourceMtime = (await stat(file)).mtimeMs;
  for (const w of WIDTHS) {
    if (!width || w >= width * 0.9) continue;
    const target = file.replace(/\.webp$/i, `-${w}.webp`);
    if (await isFresh(target, sourceMtime)) continue;
    await sharp(file).resize({ width: w }).webp({ quality: QUALITY }).toFile(target);
    const kb = ((await stat(target)).size / 1024).toFixed(0);
    console.log(`${path.relative(ROOT, target).replace(/\\/g, "/")}  ${kb} KB`);
    made++;
  }
}
console.log(made ? `Done. ${made} variant(s) written.` : "All variants up to date.");
