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

test("product catalog uses the canonical API client with page one omitted", async () => {
  const previous = process.env.HOMEPAGE_DATA_SOURCE;
  delete process.env.HOMEPAGE_DATA_SOURCE;
  const calls = [];
  const apiRequest = async (request) => {
    calls.push(request);
    return { data: [], meta: { pagination: { current_page: 1, per_page: 20, total: 0, last_page: 1, has_next: false, has_previous: false } } };
  };

  try {
    const { getProductCatalog } = loadTs("lib/products/catalog.ts", { "../api/client": { apiRequest } });
    const firstPage = await getProductCatalog(1);
    await getProductCatalog(2);
    assert.equal(firstPage.source, "api");
    assert.deepEqual(calls[0], { path: "/products", cache: "no-store" });
    assert.deepEqual(calls[1].query, { page: 2 });
  } finally {
    if (previous === undefined) delete process.env.HOMEPAGE_DATA_SOURCE;
    else process.env.HOMEPAGE_DATA_SOURCE = previous;
  }
});

test("product catalog does not replace an API failure with fixture data", async () => {
  const previous = process.env.HOMEPAGE_DATA_SOURCE;
  delete process.env.HOMEPAGE_DATA_SOURCE;
  const failure = new Error("upstream unavailable");

  try {
    const { getProductCatalog } = loadTs("lib/products/catalog.ts", { "../api/client": { apiRequest: async () => { throw failure; } } });
    await assert.rejects(getProductCatalog(1), (error) => error === failure);
  } finally {
    if (previous === undefined) delete process.env.HOMEPAGE_DATA_SOURCE;
    else process.env.HOMEPAGE_DATA_SOURCE = previous;
  }
});

test("product listing stays server-first, reuses shared catalog primitives, and keeps phase boundaries", () => {
  const page = read("app/products/page.tsx");
  const grid = read("components/catalog/product-grid.tsx");
  const card = read("components/catalog/product-card.tsx");
  const navigation = read("components/layout/site-navigation.ts");
  const proxy = read("proxy.ts");
  assert.match(page, /getProductCatalog/);
  assert.match(page, /ProductGrid/);
  assert.match(page, /Page \{currentPage\} of \{lastPage\}/);
  assert.equal((page.match(/component="h1"/g) ?? []).length, 1);
  assert.doesNotMatch(page, /["']use client["']|notFound\(\)|<main|component="main"|href="#"|\/cart|\/checkout|\/payment|wishlist|Buy now|filter|sort/);
  assert.match(grid, /ProductCard/);
  assert.match(card, /var\(--media-product-card\)/);
  assert.match(navigation, /"\/products"/);
  assert.match(proxy, /"\/categories\/:path\*"/);
  assert.match(proxy, /"\/products\/:slug"/);
});

test("product listing pagination helpers normalize malformed pages and preserve the clean first-page URL", () => {
  const page = read("app/products/page.tsx");
  assert.match(page, /function parsePage/);
  assert.ok(page.includes("/^[1-9]\\d*$/"));
  assert.match(page, /return page === 1 \? "\/products" : `\/products\?page=\$\{page\}`/);
});

test("product detail uses CAT-002 data, activates canonical card links, and preserves request-only boundaries", () => {
  const page = read("app/products/[slug]/page.tsx");
  const imageSizes = read("lib/homepage/image-sizes.ts");
  const detail = read("lib/products/detail.ts");
  const grid = read("components/catalog/product-grid.tsx");
  const proxy = read("proxy.ts");
  assert.match(page, /getProductDetail/);
  assert.match(page, /notFound\(\)/);
  assert.match(page, /formatMoney/);
  assert.equal((page.match(/component="h1"/g) ?? []).length, 1);
  assert.doesNotMatch(page, /["']use client["']|\/cart|\/checkout|\/payment|Buy now|Add to cart|wishlist/);
  assert.match(detail, /path: `products\/\$\{encodeURIComponent\(slug\)\}`/);
  assert.match(detail, /cache: "no-store"/);
  assert.match(grid, /href=\{`\/products\/\$\{product\.slug\}`\}/);
  assert.match(proxy, /"\/products\/:slug"/);
  assert.match(proxy, /resource === "products"/);
  assert.match(page, /stockIndicator === "MADE_TO_ORDER"\) label = "Made to order"/);
  assert.match(page, /PRODUCT_DETAIL_IMAGE_SIZES/);
  assert.match(imageSizes, /export const PRODUCT_DETAIL_IMAGE_SIZES/);
});
