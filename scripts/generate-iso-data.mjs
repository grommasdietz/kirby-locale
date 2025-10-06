#!/usr/bin/env node

import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

import "@formatjs/intl-locale/polyfill.js";
import "@formatjs/intl-displaynames/polyfill.js";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, "..");

const dataPath = path.join(rootDir, "resources", "source", "iso-639-1.json");
const phpCatalogPath = path.join(rootDir, "resources", "iso-639-1.php");
const jsonTranslationsPath = path.join(
  rootDir,
  "src",
  "utils",
  "isoTranslations.json"
);

const escapePhpString = (value) =>
  value.replace(/\\/g, "\\\\").replace(/'/g, "\\'");

const normaliseLocaleTag = (value) =>
  typeof value === "string"
    ? value.trim().replace(/_/g, "-").replace(/\s+/g, "")
    : "";

const canonicaliseLocale = (value) => {
  const tag = normaliseLocaleTag(value);

  if (!tag) {
    return "";
  }

  if (
    typeof Intl !== "undefined" &&
    typeof Intl.getCanonicalLocales === "function"
  ) {
    try {
      const result = Intl.getCanonicalLocales(tag);

      if (Array.isArray(result) && result.length > 0) {
        return result[0];
      }
    } catch (error) {
      // ignore and fall back below
    }
  }

  return tag;
};

const localeCandidateKeys = (value) => {
  const canonical = canonicaliseLocale(value);

  if (!canonical) {
    return [];
  }

  const lowered = canonical.toLowerCase();
  const candidates = new Set([lowered]);
  const [base] = lowered.split("-");

  if (base && base !== lowered) {
    candidates.add(base);
  }

  if (lowered === "nb" && base !== "no") {
    candidates.add("no");
  }

  return Array.from(candidates);
};

const loadIsoCatalog = () => {
  if (fs.existsSync(dataPath) === false) {
    throw new Error(`Missing ISO dataset at ${dataPath}`);
  }

  const raw = fs.readFileSync(dataPath, "utf8");
  const data = JSON.parse(raw);

  return data
    .map((entry) => ({
      code: String(entry.code || "")
        .trim()
        .toLowerCase(),
      name: typeof entry.name === "string" ? entry.name.trim() : "",
    }))
    .filter((entry) => entry.code !== "")
    .sort((a, b) => a.code.localeCompare(b.code));
};

const collectPanelLocales = () => {
  const translationsDir = path.join(rootDir, "translations");
  const locales = new Set(["en"]);

  if (fs.existsSync(translationsDir)) {
    for (const file of fs.readdirSync(translationsDir)) {
      if (file.endsWith(".php")) {
        locales.add(file.replace(/\.php$/i, ""));
      }
    }
  }

  return orderLocales(Array.from(locales));
};

const orderLocales = (locales) => {
  const sorted = Array.from(new Set(locales)).sort();
  const index = sorted.indexOf("en");

  if (index > 0) {
    sorted.splice(index, 1);
    sorted.unshift("en");
  }

  return sorted;
};

const loadedLocaleData = new Set();

const ensureLocaleData = async (locale) => {
  const attempts = new Set();
  const canonical = canonicaliseLocale(locale);
  const lowered = canonical.toLowerCase();

  if (canonical) {
    attempts.add(canonical);
  }

  if (lowered !== canonical) {
    attempts.add(lowered);
  }

  const [base] = canonical.split("-");

  if (base && base !== canonical) {
    attempts.add(base);

    const lowerBase = base.toLowerCase();

    if (lowerBase !== base) {
      attempts.add(lowerBase);
    }
  }

  if (lowered === "nb") {
    attempts.add("no");
  }

  for (const attempt of attempts) {
    if (loadedLocaleData.has(attempt)) {
      continue;
    }

    try {
      await import(`@formatjs/intl-displaynames/locale-data/${attempt}.js`);
      loadedLocaleData.add(attempt);
    } catch (error) {
      console.warn(
        `[generate-iso-data] Locale data for "${attempt}" is unavailable (${error.message}).`
      );
      loadedLocaleData.add(attempt);
    }
  }
};

const createDisplayNamesCache = () => {
  const cache = new Map();

  return (locale) => {
    const key = locale.toLowerCase();

    if (cache.has(key)) {
      return cache.get(key);
    }

    try {
      const instance = new Intl.DisplayNames([locale], { type: "language" });
      cache.set(key, instance);
      return instance;
    } catch (error) {
      cache.set(key, null);
      return null;
    }
  };
};

const resolveDisplayName = (getDisplayNames, locale, code, fallback) => {
  const candidates = [];
  const normalised = locale.toLowerCase();

  candidates.push(normalised);

  const [base] = normalised.split("-");

  if (base && base !== normalised) {
    candidates.push(base);
  }

  if (normalised === "nb" && base !== "no") {
    candidates.push("no");
  }

  candidates.push("en");

  for (const candidate of candidates) {
    const displayNames = getDisplayNames(candidate);

    if (!displayNames) {
      continue;
    }

    const rendered = displayNames.of(code);

    if (
      typeof rendered === "string" &&
      rendered.trim() !== "" &&
      rendered !== code
    ) {
      return rendered.trim();
    }
  }

  const fallbackName = fallback.get(code);
  return typeof fallbackName === "string" && fallbackName !== ""
    ? fallbackName
    : code;
};

const writePhpCatalog = (records, englishMap) => {
  const lines = ["<?php", "", "return ["];

  for (const record of records) {
    const name = englishMap.get(record.code) || record.name || record.code;
    lines.push(
      `    ['code' => '${record.code}', 'name' => '${escapePhpString(name)}'],`
    );
  }

  lines.push("];", "");

  fs.writeFileSync(phpCatalogPath, lines.join("\n"));
};

const writeJsonTranslations = (translations) => {
  const ordered = orderLocales(Object.keys(translations)).reduce(
    (acc, locale) => {
      const codes = Object.keys(translations[locale])
        .sort()
        .reduce((map, code) => {
          map[code] = translations[locale][code];
          return map;
        }, {});

      acc[locale] = codes;
      return acc;
    },
    {}
  );

  fs.writeFileSync(
    jsonTranslationsPath,
    `${JSON.stringify(ordered, null, 4)}\n`
  );
};

const main = async () => {
  const isoCatalog = loadIsoCatalog();
  const panelLocales = collectPanelLocales();

  const fallback = new Map(isoCatalog.map((entry) => [entry.code, entry.name]));
  const getDisplayNames = createDisplayNamesCache();

  for (const locale of panelLocales) {
    await ensureLocaleData(locale);
  }

  const translations = {};

  for (const locale of panelLocales) {
    translations[locale] = {};

    for (const record of isoCatalog) {
      const label = resolveDisplayName(
        getDisplayNames,
        locale,
        record.code,
        fallback
      );

      translations[locale][record.code] = label;
    }
  }

  writePhpCatalog(isoCatalog, new Map(Object.entries(translations.en)));
  writeJsonTranslations(translations);

  console.log(
    `[generate-iso-data] Generated translations for ${panelLocales.length} panel locale(s).`
  );
};

main().catch((error) => {
  console.error("[generate-iso-data] Failed:", error);
  process.exitCode = 1;
});
