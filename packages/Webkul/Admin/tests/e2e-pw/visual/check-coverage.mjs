#!/usr/bin/env node
/**
 * Hold the baselines on disk to the inventory the harness declares.
 *
 *     npm run vr:coverage
 *
 * This is the third command of the harness, and the only one that needs no browser. It reads
 * `inventory.json` — written by `inventory.spec.ts` from the same registry the captures use — and
 * answers three questions with an exit code:
 *
 * 1. Does every declared cell exist on disk, and is it a real image rather than a zero-byte file?
 * 2. Is the cross-product what the registry claims (surfaces × widths × modes), and which surfaces
 *    are declared narrower than the full matrix?
 * 3. Is there anything on disk the inventory does not know about? An orphan is a baseline that no
 *     comparison will ever look at, so it is worse than useless: it is a false sense of coverage.
 *
 * Plain Node, no dependencies, so it runs in CI before Playwright is installed if that is ever
 * useful. It fails closed: a missing inventory is an error, not an empty pass.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));

const INVENTORY = path.join(here, "inventory.json");

const BASELINES = path.join(here, "baselines");

/** A PNG's first eight bytes; a file that does not start with them is not an image. */
const PNG_MAGIC = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);

function fail(lines) {
    for (const line of lines) {
        console.error(line);
    }

    process.exit(1);
}

if (! fs.existsSync(INVENTORY)) {
    fail([
        `No inventory at ${ path.relative(process.cwd(), INVENTORY) }.`,
        "Run `npm run vr:baseline` (or `npm run vr:compare`) once so inventory.spec.ts writes it.",
    ]);
}

const inventory = JSON.parse(fs.readFileSync(INVENTORY, "utf8"));

/**
 * Skipped cells are declared, printed and counted — but they are not required to exist, because a
 * surface that is knowingly not captured must not be able to fail this check (and must not be able to
 * pass it silently either: it is listed, with its reason, in the summary and in `INVENTORY.md`).
 */
const skipped = inventory.cells.filter((cell) => cell.skipped);

const required = inventory.cells.filter((cell) => ! cell.skipped);

const missing = [];
const empty = [];
const notPng = [];

for (const cell of required) {
    const file = path.join(here, cell.file);

    if (! fs.existsSync(file)) {
        missing.push(cell);
        continue;
    }

    const bytes = fs.readFileSync(file);

    if (bytes.length === 0) {
        empty.push(cell);
        continue;
    }

    if (! bytes.subarray(0, 8).equals(PNG_MAGIC)) {
        notPng.push(cell);
    }
}

const declared = new Set(required.map((cell) => cell.file));

const onDisk = [];

const walk = (directory) => {
    if (! fs.existsSync(directory)) {
        return;
    }

    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
        const full = path.join(directory, entry.name);

        if (entry.isDirectory()) {
            walk(full);
        } else if (entry.name.endsWith(".png")) {
            onDisk.push(path.relative(here, full));
        }
    }
};

walk(BASELINES);

const orphans = onDisk.filter((file) => ! declared.has(file));

const found = required.length - missing.length;

const fullCrossProduct = inventory.surfaceCount * inventory.widths.length * inventory.modes.length;

console.log(`locale:   ${ inventory.locale }`);
console.log(`widths:   ${ inventory.widths.join(", ") } px`);
console.log(`modes:    ${ inventory.modes.join(", ") }`);
console.log(`surfaces: ${ inventory.surfaceCount }  (${ fullCrossProduct } cells at the full cross-product)`);
console.log(`declared: ${ required.length } cells${ skipped.length ? ` (+ ${ skipped.length } skipped)` : "" }`);
console.log(`on disk:  ${ found } / ${ required.length }`);
console.log("");

for (const surface of inventory.surfaces) {
    const present = inventory.widths.map((width) => (surface.widths.includes(width) ? width : "—")).join(" | ");

    const surfaceCells = inventory.cells.filter((cell) => cell.surface === surface.slug);

    const captured = surfaceCells.filter(
        (cell) => ! cell.skipped && ! missing.some((gone) => gone.file === cell.file)
    ).length;

    const skippedHere = surfaceCells.filter((cell) => cell.skipped).length;

    console.log(
        `  ${ surface.slug.padEnd(20) } ${ String(captured).padStart(2) }/${ String(surface.cells).padEnd(2) }${
            skippedHere ? `  SKIPPED (${ skippedHere })` : ""
        }  widths ${ present }${
            surface.widths.length < inventory.widths.length ? "  (narrower than the matrix, by design)" : ""
        }${ surface.honoursDark ? "" : "  (both modes are the same light document)" }`
    );
}

if (orphans.length) {
    console.log("");
    console.log(`orphans (on disk, not in the inventory): ${ orphans.length }`);
    for (const orphan of orphans) {
        console.log(`  ${ orphan }`);
    }
}

if (missing.length || empty.length || notPng.length || orphans.length) {
    console.log("");
}

if (missing.length) {
    console.log(`MISSING: ${ missing.length } declared cells have no baseline:`);
    for (const cell of missing) {
        console.log(`  ${ cell.file }  (${ cell.surface } @ ${ cell.width } ${ cell.mode })`);
    }
}

if (empty.length) {
    console.log(`EMPTY: ${ empty.length } baselines are zero bytes:`);
    for (const cell of empty) {
        console.log(`  ${ cell.file }`);
    }
}

if (notPng.length) {
    console.log(`NOT A PNG: ${ notPng.length } baselines do not start with the PNG signature:`);
    for (const cell of notPng) {
        console.log(`  ${ cell.file }`);
    }
}

if (missing.length || empty.length || notPng.length || orphans.length) {
    fail([`coverage FAILED: ${ missing.length } missing, ${ empty.length } empty, ${ notPng.length } not-a-png, ${ orphans.length } orphaned.`]);
}

if (skipped.length) {
    console.log("");
    console.log(`skipped (declared, not captured): ${ skipped.length } cells`);
    for (const slug of [...new Set(skipped.map((cell) => cell.surface))]) {
        const reason = skipped.find((cell) => cell.surface === slug).skipReason;
        console.log(`  ${ slug } — ${ reason }`);
    }
}

console.log("");
console.log(
    `coverage OK: all ${ required.length } declared baselines exist and are valid PNGs` +
        (skipped.length ? `; ${ skipped.length } cells are declared but skipped.` : ".")
);
