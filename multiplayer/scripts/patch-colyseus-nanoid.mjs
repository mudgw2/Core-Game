import { readFileSync, writeFileSync } from "node:fs";

const file = "node_modules/@colyseus/core/build/utils/Utils.mjs";
const original = 'import nanoid from "nanoid";';
const patched = 'import { nanoid } from "nanoid";';

let source = readFileSync(file, "utf8");

if (source.includes(patched)) {
  console.log("Colyseus Nanoid compatibility patch already applied.");
} else if (source.includes(original)) {
  source = source.replace(original, patched);
  writeFileSync(file, source);
  console.log("Applied Colyseus Nanoid compatibility patch.");
} else {
  console.error("Unexpected Colyseus Utils.mjs import; refusing to patch.");
  process.exit(1);
}
