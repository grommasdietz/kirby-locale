#!/usr/bin/env node
import fs from "node:fs";

const errors = [];
const markers = ["grommasdietz/locale", "title_locale", "notranslate"];
if (!fs.existsSync("index.js") || fs.statSync("index.js").size === 0) {
  errors.push("index.js is missing or empty");
} else {
  const bundle = fs.readFileSync("index.js", "utf8");
  for (const marker of markers) {
    if (!bundle.includes(marker)) errors.push(`index.js is missing runtime marker: ${marker}`);
  }
}
if (!fs.existsSync("index.css")) errors.push("index.css is missing");
if (errors.length > 0) {
  console.error("Panel bundle verification failed:");
  for (const error of errors) console.error(` - ${error}`);
  process.exit(1);
}
console.log("Panel bundle verification passed.");
