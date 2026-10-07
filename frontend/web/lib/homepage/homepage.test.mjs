import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { createRequire } from "node:module";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import test from "node:test";
import { renderToStaticMarkup } from "react-dom/server";
import { createElement } from "react";
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
      const extension = specifier.endsWith(".json") ? "" : target.endsWith("card") || target.endsWith("nav-link") ? ".tsx" : ".ts";
      return extension ? loadTs(target + extension, overrides) : require(target);
    }
    return require(specifier);
  };
  new Function("require", "module", "exports", output)(importer, compiled, compiled.exports);
  return compiled.exports;
}

test("money preserves TZS minor units, including cents, and rejects invalid data", () => {
  const { formatMoney } = loadTs("lib/catalog/format-money.ts");
  assert.match(formatMoney({ amount: 125000000, currency: "TZS" }), /TZS\s+1,250,000\.00/);
  assert.match(formatMoney({ amount: 101, currency: "TZS" }), /TZS\s+1\.01/);
  assert.match(formatMoney({ amount: 0, currency: "TZS" }), /TZS\s+0\.00/);
  for (const amount of [-1, 1.5, Number.MAX_SAFE_INTEGER + 1]) {
    assert.throws(() => formatMoney({ amount, currency: "TZS" }), RangeError);
  }
});

test("canonical ProductCard renders media as data, request state and safe deferred links", () => {
  const { ProductCard } = loadTs("components/catalog/product-card.tsx");
  const product = { name: "A considered chair", price: { amount: 101, currency: "TZS" }, product_type: "MADE_TO_ORDER", availability: "unavailable", stock_indicator: "MADE_TO_ORDER", primary_image: { url: "/sample.jpg", alt_text: "Wooden chair with cream cushions" } };
  const markup = renderToStaticMarkup(createElement(ProductCard, { product, sizes: "33vw", href: "/products/considered-chair" }));
  assert.match(markup, /<article/);
  assert.match(markup, /<h3/);
  assert.match(markup, /Wooden chair with cream cushions/);
  assert.match(markup, /sizes="33vw"/);
  assert.match(markup, /loading="lazy"/);
  assert.match(markup, /Made to order/);
  assert.match(markup, /From TZS/);
  assert.match(markup, /<a href="\/products\/considered-chair"/);
  assert.doesNotMatch(markup, /Currently unavailable|Mui-error|fixtures/);
  const missing = renderToStaticMarkup(createElement(ProductCard, { product: { ...product, primary_image: null }, sizes: "33vw" }));
  assert.match(missing, /Photograph to follow/);
  assert.match(missing, /A considered chair/);
  const lowStock = renderToStaticMarkup(createElement(ProductCard, { product: { ...product, product_type: "IN_STOCK", availability: "available", stock_indicator: "LOW_STOCK" }, sizes: "33vw" }));
  assert.match(lowStock, /Low stock/);
});

test("homepage reads the public API by default and never falls back to fixtures", async () => {
  const previous = process.env.HOMEPAGE_DATA_SOURCE;
  delete process.env.HOMEPAGE_DATA_SOURCE;
  const calls = [];
  const apiRequest = async (request) => { calls.push(request); return { data: [] }; };
  try {
    const { getHomepageCatalog } = loadTs("lib/homepage/catalog.ts", { "../api/client": { apiRequest } });
    const result = await getHomepageCatalog();
    assert.equal(result.source, "api");
    assert.deepEqual(result.products, []);
    assert.equal(calls.length, 2);
    assert.ok(calls.every((call) => call.cache === "no-store"));
    assert.equal(calls[1].query.product_type, "MADE_TO_ORDER");
    const failure = new Error("upstream unavailable");
    const broken = loadTs("lib/homepage/catalog.ts", { "../api/client": { apiRequest: async () => { throw failure; } } });
    await assert.rejects(broken.getHomepageCatalog(), (error) => error === failure);
    process.env.HOMEPAGE_DATA_SOURCE = "fixtures";
    const preview = await broken.getHomepageCatalog();
    assert.equal(preview.source, "fixtures");
    assert.equal(preview.products.length, 3);
    process.env.HOMEPAGE_DATA_SOURCE = "unexpected";
    await assert.rejects(broken.getHomepageCatalog(), /Invalid homepage data source/);
  } finally {
    if (previous === undefined) delete process.env.HOMEPAGE_DATA_SOURCE;
    else process.env.HOMEPAGE_DATA_SOURCE = previous;
  }
});

test("homepage respects frozen tokens, shell, server and request-first boundaries", () => {
  const page = read("app/page.tsx");
  const card = read("components/catalog/product-card.tsx");
  const discovery = read("components/catalog/catalog-discovery.tsx");
  const navLink = read("components/layout/nav-link.tsx");
  assert.equal((page.match(/component="h1"/g) ?? []).length, 1);
  assert.doesNotMatch(page + card + discovery, /["']use client["']|<main|component="main"|<img|href="#"|#[0-9a-f]{3,8}\b|\d+px|boxShadow|borderRadius|window\.innerWidth|\/cart|\/checkout|\/payment|HomepageProductCard/);
  assert.match(navLink, /textDecoration: "none"/);
  assert.match(navLink, /color: "inherit"/);
  assert.match(navLink, /"& a:hover": \{\s*textDecoration: "underline"/);
  assert.match(page, /SiteSection/);
  assert.match(page, /Design preview/);
  assert.match(card, /var\(--media-product-card\)/);
  assert.doesNotMatch(card, /\/furnitures\/|\.jpg/);
  for (const id of ["rooms", "selected-furniture", "made-to-order"]) assert.match(page + discovery, new RegExp(`id="${id}"`));
});
