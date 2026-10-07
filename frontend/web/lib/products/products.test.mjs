import assert from "node:assert/strict";
import test from "node:test";
import { loadTs, read, withApiDataSource } from "../test-utils/load-ts.mjs";

test("product catalog uses the canonical API client with page one omitted", async () => {
  await withApiDataSource(async () => {
    const calls = [];
    const apiRequest = async (request) => {
      calls.push(request);
      return { data: [], meta: { pagination: { current_page: 1, per_page: 20, total: 0, last_page: 1, has_next: false, has_previous: false } } };
    };
    const { getProductCatalog } = loadTs("lib/products/catalog.ts", { "../api/client": { apiRequest } });
    const firstPage = await getProductCatalog(1);
    await getProductCatalog(2);
    assert.equal(firstPage.source, "api");
    assert.deepEqual(calls[0], { path: "/products", cache: "no-store" });
    assert.deepEqual(calls[1].query, { page: 2 });
  });
});

test("product catalog does not replace an API failure with fixture data", async () => {
  await withApiDataSource(async () => {
    const failure = new Error("upstream unavailable");
    const { getProductCatalog } = loadTs("lib/products/catalog.ts", { "../api/client": { apiRequest: async () => { throw failure; } } });
    await assert.rejects(getProductCatalog(1), (error) => error === failure);
  });
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
