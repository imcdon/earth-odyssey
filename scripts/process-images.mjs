#!/usr/bin/env node
// Bulk-optimize photos for Earth Odyssey (Soldotna-style pipeline).
// Drop raw photos in _raw/images/<folder>/ (jpg/JPG/heic/HEIC/png/webp/etc.) and run `npm run media:images`.
// Outputs go to assets/img/... as lowercase .webp files at quality 82, max width 2400px.

import { mkdir, readFile, readdir, stat } from "node:fs/promises";
import path from "node:path";
import { createRequire } from "node:module";
import sharp from "sharp";

const require = createRequire(import.meta.url);
/** Lazy — only loads when Sharp cannot decode HEIC (common on Windows). */
let _heicConvert;
function loadHeicConvert() {
  if (!_heicConvert) {
    _heicConvert = require("heic-convert");
  }
  return _heicConvert;
}

const IN_ROOT = path.resolve(process.cwd(), "_raw/images");
const OUT_ROOT = path.resolve(process.cwd(), "assets/img");
const MAX_WIDTH = 2400;
const QUALITY = 82;

/** Match walk filter (case-insensitive). */
const INPUT_EXT_REGEX = /\.(jpe?g|png|tiff?|webp|hei[cf])$/i;

function extPriority(filePath) {
  const ext = path.extname(filePath).toLowerCase();
  if (ext === ".jpg" || ext === ".jpeg") return 100;
  if (ext === ".png") return 90;
  if (ext === ".webp") return 80;
  if (ext === ".heic" || ext === ".heif") return 70;
  if (ext === ".tif" || ext === ".tiff") return 65;
  return 50;
}

/** Stable key so day-hike.JPG and day-hike.jpg dedupe against the same output. */
function dedupeKey(relPath) {
  const norm = relPath.split(path.sep).join("/");
  const dir = path.posix.dirname(norm);
  const stem = path.posix.basename(norm, path.extname(norm)).toLowerCase();
  return dir === "." ? stem : `${dir}/${stem}`;
}

/** Output path for a normalized relative dirname (preserve case) + lowercased basename stem. */
function outputPath(relPath) {
  const dirRel = path.dirname(relPath);
  const stem = path.basename(relPath, path.extname(relPath)).toLowerCase();
  const name = `${stem}.webp`;
  return dirRel === "." ? path.join(OUT_ROOT, name) : path.join(OUT_ROOT, dirRel, name);
}

function compareCandidates(a, b) {
  const dp = extPriority(b.path) - extPriority(a.path);
  if (dp !== 0) return dp;
  if (b.mtimeMs !== a.mtimeMs) return b.mtimeMs - a.mtimeMs;
  return a.path.localeCompare(b.path);
}

async function walk(dir) {
  const out = [];
  let entries;
  try {
    entries = await readdir(dir, { withFileTypes: true });
  } catch (e) {
    if (e.code === "ENOENT") return out;
    throw e;
  }
  for (const entry of entries) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      out.push(...(await walk(full)));
    } else if (INPUT_EXT_REGEX.test(entry.name)) {
      out.push(full);
    }
  }
  return out;
}

async function encodeToWebpFromSharpInput(source, outPath) {
  const meta = await sharp(source).metadata();
  const w = meta.width ?? MAX_WIDTH;
  await mkdir(path.dirname(outPath), { recursive: true });
  await sharp(source)
    .rotate()
    .resize({ width: Math.min(w, MAX_WIDTH), withoutEnlargement: true })
    .webp({ quality: QUALITY })
    .toFile(outPath);
}

async function encodeHeicFile(inputPath, rel, outPath) {
  const buf = await readFile(inputPath);
  const convert = loadHeicConvert();
  let jpegBuf;
  try {
    jpegBuf = await convert({ buffer: buf, format: "JPEG", quality: 1 });
  } catch (e) {
    console.error(`heic-convert failed for ${rel}:`, e.message || e);
    throw e;
  }
  await encodeToWebpFromSharpInput(jpegBuf, outPath);
}

async function processFile(inputPath) {
  const rel = path.relative(IN_ROOT, inputPath);
  const outPath = outputPath(rel);
  const extLow = path.extname(inputPath).toLowerCase();

  try {
    await encodeToWebpFromSharpInput(inputPath, outPath);
  } catch (e) {
    const sharpHeic = extLow === ".heic" || extLow === ".heif";
    if (!sharpHeic) throw e;
    console.warn(`Sharp HEIC decode failed (${rel}); using heic-convert fallback`);
    await encodeHeicFile(inputPath, rel, outPath);
  }

  const inSize = (await stat(inputPath)).size;
  const outSize = (await stat(outPath)).size;
  console.log(
    `${rel.replace(/\\/g, "/")}  ${(inSize / 1024 / 1024).toFixed(2)}MB → ${(outSize / 1024 / 1024).toFixed(2)}MB`,
  );
}

const discovered = await walk(IN_ROOT);
if (discovered.length === 0) {
  console.log(`No images found in ${IN_ROOT}. Drop raw photos there and re-run.`);
  process.exit(0);
}

/** Dedupe paths that normalize to the same output. */
const byKey = new Map();
for (const full of discovered) {
  const rel = path.relative(IN_ROOT, full);
  const key = dedupeKey(rel);
  let list = byKey.get(key);
  if (!list) {
    list = [];
    byKey.set(key, list);
  }
  const s = await stat(full);
  list.push({ path: full, mtimeMs: s.mtimeMs });
}

/** @type {string[]} */
const toProcess = [];
for (const [key, candidates] of byKey) {
  candidates.sort(compareCandidates);
  const winner = candidates[0];
  toProcess.push(winner.path);
  if (candidates.length > 1) {
    const skipped = candidates.slice(1).map((c) => path.relative(IN_ROOT, c.path).replace(/\\/g, "/"));
    console.warn(
      `Deduped (${key.replace(/\\/g, "/")}): using ${path.relative(IN_ROOT, winner.path).replace(/\\/g, "/")}; skipped duplicates: ${skipped.join(", ")}`,
    );
  }
}

console.log(`Processing ${toProcess.length} image(s) (from ${discovered.length} raw file(s)) …`);
for (const f of toProcess) {
  try {
    await processFile(f);
  } catch (e) {
    console.error(`Failed: ${f}`, e);
  }
}
console.log("Done.");
