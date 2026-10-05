#!/usr/bin/env node
import { execFileSync } from "node:child_process";
import crypto from "node:crypto";
import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const requested = process.argv.slice(2);
const targets = requested.length > 0 ? requested : ["index.js", "index.css"];

function collect(target, files = []) {
  const absolute = path.resolve(root, target);
  if (!fs.existsSync(absolute)) return files;
  const stat = fs.lstatSync(absolute);
  if (stat.isSymbolicLink()) return files;
  if (stat.isFile()) {
    files.push(path.relative(root, absolute));
    return files;
  }
  for (const entry of fs.readdirSync(absolute, { withFileTypes: true })) {
    collect(path.join(target, entry.name), files);
  }
  return files;
}

const filesForTargets = () => [...new Set(targets.flatMap((target) => collect(target)))].sort();
const digest = (bytes) => crypto.createHash("sha256").update(bytes).digest("hex");
function packageManagerInvocation(args) {
  if (process.env.npm_execpath) return [process.execPath, [process.env.npm_execpath, ...args]];
  return [process.platform === "win32" ? "pnpm.cmd" : "pnpm", args];
}

const beforeFiles = filesForTargets();
if (beforeFiles.length === 0) {
  console.error("No generated assets found. Adjust build:reproduce targets in package.json.");
  process.exit(1);
}

const before = new Map(beforeFiles.map((file) => [file, fs.readFileSync(path.join(root, file))]));
let buildError = null;
const changed = [];

try {
  const [command, args] = packageManagerInvocation(["run", "build:assets"]);
  execFileSync(command, args, { cwd: root, stdio: "inherit", env: process.env });
  const afterFiles = filesForTargets();
  const allFiles = new Set([...beforeFiles, ...afterFiles]);
  for (const file of allFiles) {
    const previous = before.get(file);
    const absolute = path.join(root, file);
    if (!previous) changed.push(`${file} (new)`);
    else if (!fs.existsSync(absolute)) changed.push(`${file} (removed)`);
    else if (digest(previous) !== digest(fs.readFileSync(absolute))) changed.push(file);
  }
} catch (error) {
  buildError = error;
} finally {
  for (const file of filesForTargets()) {
    if (!before.has(file)) fs.rmSync(path.join(root, file), { force: true });
  }
  for (const [file, bytes] of before) {
    const absolute = path.join(root, file);
    fs.mkdirSync(path.dirname(absolute), { recursive: true });
    fs.writeFileSync(absolute, bytes);
  }
}

if (buildError) {
  console.error("Generated-asset reproducibility build failed.");
  process.exit(typeof buildError.status === "number" ? buildError.status : 1);
}
if (changed.length > 0) {
  console.error("Generated assets are not byte-reproducible in this environment:");
  for (const file of changed) console.error(` - ${file}`);
  console.error("Run the normal build in the canonical release environment and review the diff.");
  process.exit(1);
}
console.log(`Generated assets are byte-reproducible (${beforeFiles.length} files).`);
