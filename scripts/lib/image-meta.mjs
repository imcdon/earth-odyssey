/*
 * Shared EXIF helpers: Make / Model / DateTimeOriginal → sidecar JSON beside each WebP.
 */
import { writeFile } from "node:fs/promises";
import path from "node:path";
import exifr from "exifr";

function cleanString(value) {
  if (value == null) return null;
  const text = String(value).replace(/\0/g, "").trim();
  return text === "" ? null : text;
}

function toIsoLocal(value) {
  if (!value) return null;
  if (value instanceof Date && !Number.isNaN(value.getTime())) {
    const pad = (n) => String(n).padStart(2, "0");
    return (
      `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())}` +
      `T${pad(value.getHours())}:${pad(value.getMinutes())}:${pad(value.getSeconds())}`
    );
  }
  const text = cleanString(value);
  if (!text) return null;
  // EXIF often uses "YYYY:MM:DD HH:MM:SS"
  const m = text.match(/^(\d{4}):(\d{2}):(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/);
  if (m) {
    return `${m[1]}-${m[2]}-${m[3]}T${m[4]}:${m[5]}:${m[6]}`;
  }
  const parsed = new Date(text);
  if (!Number.isNaN(parsed.getTime())) {
    return toIsoLocal(parsed);
  }
  return null;
}

export function sidecarPathForWebp(webpPath) {
  return webpPath.replace(/\.webp$/i, ".json");
}

export async function extractCaptureMeta(source) {
  let parsed;
  try {
    // Keep EXIF date strings (camera-local), not revived UTC Date objects.
    parsed = await exifr.parse(source, {
      pick: ["Make", "Model", "DateTimeOriginal", "CreateDate", "DateTime", "ModifyDate"],
      reviveValues: false,
    });
  } catch {
    return null;
  }
  if (!parsed || typeof parsed !== "object") {
    return null;
  }

  const make = cleanString(parsed.Make);
  const model = cleanString(parsed.Model);
  const takenAt =
    toIsoLocal(parsed.DateTimeOriginal) ||
    toIsoLocal(parsed.CreateDate) ||
    toIsoLocal(parsed.DateTime) ||
    toIsoLocal(parsed.ModifyDate);

  if (!make && !model && !takenAt) {
    return null;
  }

  return {
    make,
    model,
    datetime_original: takenAt,
  };
}

export async function writeCaptureSidecar(webpPath, meta) {
  const out = sidecarPathForWebp(webpPath);
  const payload = {
    make: meta?.make ?? null,
    model: meta?.model ?? null,
    datetime_original: meta?.datetime_original ?? null,
  };
  await writeFile(out, `${JSON.stringify(payload, null, 2)}\n`, "utf8");
  return out;
}
