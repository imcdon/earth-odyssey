#!/usr/bin/env node
// Write Make/Model/DateTimeOriginal JSON sidecars next to existing WebPs from _raw/images sources.
// Does not re-encode images. Run: npm run media:meta

import { readdir, stat } from "node:fs/promises";
import path from "node:path";
import { extractCaptureMeta, writeCaptureSidecar } from "./lib/image-meta.mjs";

const IN_ROOT = path.resolve(process.cwd(), "_raw/images");
const OUT_ROOT = path.resolve(process.cwd(), "assets/img");
const SKIP_DIRS = new Set(["drive-download-20260714T021053Z-1-001"]);
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

function dedupeKey(relPath) {
  const norm = relPath.split(path.sep).join("/");
  const dir = path.posix.dirname(norm);
  const stem = path.posix.basename(norm, path.extname(norm)).toLowerCase();
  return dir === "." ? stem : `${dir}/${stem}`;
}

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
      if (SKIP_DIRS.has(entry.name)) continue;
      out.push(...(await walk(full)));
    } else if (INPUT_EXT_REGEX.test(entry.name)) {
      out.push(full);
    }
  }
  return out;
}

const discovered = await walk(IN_ROOT);
if (discovered.length === 0) {
  console.log(`No images found in ${IN_ROOT}.`);
  process.exit(0);
}

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

let wrote = 0;
let empty = 0;
for (const [, candidates] of byKey) {
  candidates.sort(compareCandidates);
  const winner = candidates[0].path;
  const rel = path.relative(IN_ROOT, winner);
  const webpPath = outputPath(rel);
  try {
    await stat(webpPath);
  } catch {
    console.warn(`Skip (no WebP yet): ${rel.replace(/\\/g, "/")}`);
    continue;
  }
  const meta = await extractCaptureMeta(winner);
  await writeCaptureSidecar(webpPath, meta);
  if (meta) {
    wrote++;
    console.log(
      `${rel.replace(/\\/g, "/")} → ${path.relative(OUT_ROOT, webpPath).replace(/\.webp$/i, ".json").replace(/\\/g, "/")}  ` +
        `${[meta.make, meta.model].filter(Boolean).join(" ") || "date only"}`,
    );
  } else {
    empty++;
    console.log(`${rel.replace(/\\/g, "/")} → sidecar (no EXIF fields)`);
  }
}

console.log(`Done. ${wrote} with EXIF, ${empty} empty sidecars.`);
