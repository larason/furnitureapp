import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { createRequire } from "node:module";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import test from "node:test";
import ts from "typescript";

const require = createRequire(import.meta.url);
const web = fileURLToPath(new URL("../../", import.meta.url));
const read = (file) => readFileSync(resolve(web, file), "utf8");

function loadTs(file, overrides = {}) {
  const path = resolve(web, file);
  const output = ts.transpileModule(readFileSync(path, "utf8"), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022, jsx: ts.JsxEmit.ReactJSX, esModuleInterop: true },
  }).outputText;
  const compiled = { exports: {} };
  const importer = (specifier) => {
    if (specifier in overrides) return overrides[specifier];
    if (specifier.startsWith(".") || specifier.startsWith("@/")) {
      const target = specifier.startsWith("@/") ? resolve(web, specifier.slice(2)) : resolve(dirname(path), specifier);
      return loadTs(target + ".ts", overrides);
    }
    return require(specifier);
  };
  new Function("require", "module", "exports", output)(importer, compiled, compiled.exports);
  return compiled.exports;
}

test("category catalog resolves the category then filters products by its API slug", async () => {
  const previous = process.env.HOMEPAGE_DATA_SOURCE;
  delete process.env.HOMEPAGE_DATA_SOURCE;
  const calls = [];
  const apiRequest = async (request) => {
    calls.push(request);
    if (request.path.startsWith("/categories/")) {
      return { data: { id: "cat_living", name: "Living Room", slug: "living-room", description: "Furniture for living spaces.", image: null } };
    }
    return { data: [], meta: { pagination: { current_page: 1, per_page: 20, total: 0, last_page: 1, has_next: false, has_previous: false } } };
  };

  try {
    const { getCategoryCatalog } = loadTs("lib/category/catalog.ts", { "../api/client": { apiRequest } });
    const catalog = await getCategoryCatalog("living-room", 1);
    assert.equal(catalog.category.slug, "living-room");
    assert.equal(calls.length, 2);
    assert.equal(calls[0].path, "/categories/living-room");
    assert.deepEqual(calls[1].query, { category: "living-room" });
  } finally {
    if (previous === undefined) delete process.env.HOMEPAGE_DATA_SOURCE;
    else process.env.HOMEPAGE_DATA_SOURCE = previous;
  }
});

test("category API failures are not replaced with fixture data", async () => {
  const previous = process.env.HOMEPAGE_DATA_SOURCE;
  delete process.env.HOMEPAGE_DATA_SOURCE;
  const failure = new Error("upstream unavailable");

  try {
    const { getCategoryCatalog } = loadTs("lib/category/catalog.ts", {
      "../api/client": { apiRequest: async () => { throw failure; } },
    });
    await assert.rejects(getCategoryCatalog("living-room", 1), (error) => error === failure);
  } finally {
    if (previous === undefined) delete process.env.HOMEPAGE_DATA_SOURCE;
    else process.env.HOMEPAGE_DATA_SOURCE = previous;
  }
});

test("category route remains server-rendered, uses the canonical card, and reserves 404 for missing categories", () => {
  const page = read("app/categories/[slug]/page.tsx");
  const proxy = read("proxy.ts");
  assert.match(page, /getCategoryCatalog/);
  assert.match(page, /ProductGrid/);
  assert.match(read("components/catalog/product-grid.tsx"), /ProductCard/);
  assert.match(page, /notFound\(\)/);
  assert.match(page, /ApiError/);
  assert.equal((page.match(/component="h1"/g) ?? []).length, 1);
  assert.doesNotMatch(page, /["']use client["']|<main|component="main"|href="#"|\/cart|\/checkout|\/payment|CategoryProductCard|ProductTile/);
  assert.match(proxy, /"\/categories\/:path\*"/);
  assert.match(proxy, /HOMEPAGE_DATA_SOURCE === "fixtures"/);
  assert.match(proxy, /NextResponse\.next\(\{ status: 404 \}\)/);
  assert.match(proxy, /status: 404/);
  assert.match(proxy, /ApiError/);
  assert.doesNotMatch(proxy, /validCategories|router\.replace|window\.location/);
});

test("homepage category destinations are active only for the implemented dynamic category route", () => {
  const { isSiteRouteImplemented } = loadTs("components/layout/site-navigation.ts");
  assert.equal(isSiteRouteImplemented("/categories/living-room"), true);
  assert.equal(isSiteRouteImplemented("/categories/living-room?page=2"), true);
  assert.equal(isSiteRouteImplemented("/products/fixture-open-frame-armchair"), true);
});

test("fixture-backed shell category navigation remains non-interactive", () => {
  assert.match(read("components/layout/primary-category-navigation.tsx"), /<NavLink href=\{`\/categories\/\$\{category\.slug\}`\} inactive/);
  assert.match(read("components/layout/mobile-navigation.tsx"), /href=\{`\/categories\/\$\{category\.slug\}`\}[\s\S]*inactive/);
  assert.match(read("components/layout/site-footer.tsx"), /inactive: true/);
  assert.match(read("components/layout/nav-link.tsx"), /inactive \|\| !isSiteRouteImplemented/);
});
