import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { fileURLToPath } from "node:url";

const checker = fileURLToPath(new URL("../tools/check-reproducible-assets.mjs", import.meta.url));
const fixture = fs.mkdtempSync(path.join(os.tmpdir(), "reproducible-assets-command-"));

try {
  fs.writeFileSync(path.join(fixture, "index.js"), "compiled fixture\n");
  const script = [
    "const assert = require('node:assert/strict');",
    "const fs = require('node:fs');",
    "assert.deepEqual(process.argv.slice(2), EXPECTED);",
    "fs.writeFileSync('build-ran', 'yes');",
  ].join("\n");
  // A native Node executable can stand in for pnpm: its first argument is a
  // fixture file named `run`, followed by the requested build script name.
  fs.writeFileSync(path.join(fixture, "run"), script.replace("EXPECTED", "['build:assets']"));
  const javascript = path.join(fixture, "package-manager.cjs");
  fs.writeFileSync(javascript, script.replace("EXPECTED", "['run', 'build:assets']"));

  for (const [kind, executable] of [["native", process.execPath], ["JavaScript", javascript]]) {
    fs.rmSync(path.join(fixture, "build-ran"), { force: true });
    const result = spawnSync(process.execPath, [checker, "index.js"], {
      cwd: fixture,
      env: { ...process.env, npm_execpath: executable },
      encoding: "utf8",
    });
    assert.equal(result.status, 0, `${kind} package-manager execution failed:\n${result.stderr}`);
    assert.equal(fs.readFileSync(path.join(fixture, "build-ran"), "utf8"), "yes");
    assert.equal(fs.readFileSync(path.join(fixture, "index.js"), "utf8"), "compiled fixture\n");
  }
  console.log("Reproducibility checks support native and JavaScript package-manager executables.");
} finally {
  fs.rmSync(fixture, { recursive: true, force: true });
}
