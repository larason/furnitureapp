import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync, readdirSync } from "node:fs";
import { join } from "node:path";
import { fileURLToPath } from "node:url";

const ADMIN_ROOT = fileURLToPath(new URL("..", import.meta.url));
const SCANNED_DIRECTORIES = ["app", "components"];

function componentFiles(directory: string): string[] {
  const entries = readdirSync(join(ADMIN_ROOT, directory), { withFileTypes: true });
  return entries.flatMap((entry) => {
    const path = join(directory, entry.name);
    if (entry.isDirectory()) return componentFiles(path);
    return entry.isFile() && path.endsWith(".tsx") ? [path] : [];
  });
}

function isClientComponent(path: string): boolean {
  return readFileSync(join(ADMIN_ROOT, path), "utf8").trimStart().startsWith('"use client"');
}

const files = SCANNED_DIRECTORIES.flatMap(componentFiles);
const passingComponentReferences = files.filter((path) => /\bcomponent=\{/.test(readFileSync(join(ADMIN_ROOT, path), "utf8")));

test("the scan covers the admin application tree", () => {
  assert.ok(files.length > 0, "expected at least one tsx file to scan");
  assert.ok(passingComponentReferences.length > 0, "expected at least one component reference to guard");
});

/**
 * `component={SomeComponent}` hands a function to a Client Component. Rendering
 * such a file from a Server Component cannot serialize that function and fails
 * at runtime with "Functions cannot be passed directly to Client Components".
 * Declaring the owner a Client Component keeps the reference legal wherever it
 * is used.
 */
test("a file passing a component reference is a client component", () => {
  const serverRendered = passingComponentReferences.filter((path) => !isClientComponent(path));

  assert.deepEqual(
    serverRendered,
    [],
    `these files pass a component reference but are not client components: ${serverRendered.join(", ")}`,
  );
});

test("the overview body, which a server page renders, is a client component", () => {
  assert.equal(isClientComponent("components/layout/admin-overview-body.tsx"), true);
});