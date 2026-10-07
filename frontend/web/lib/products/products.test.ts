import assert from "node:assert/strict";
import test from "node:test";
import type { ApiRequestOptions } from "@/lib/api/client";
import { getProductCatalog } from "@/lib/products/catalog";
import { read, stubTransport, withApiDataSource } from "../test-utils/frontend-test-helpers";

test("product catalog uses the canonical API client with page one omitted", async () => {
  await withApiDataSource(async () => {
    const calls: ApiRequestOptions[] = [];
    const apiRequest = stubTransport((request) => {
      calls.push(request);
      return { data: [], meta: { pagination: { current_page: 1, per_page: 20, total: 0, last_page: 1, has_next: false, has_previous: false } } };
    });
    const firstPage = await getProductCatalog({}, apiRequest);
    await getProductCatalog({ page: "2" }, apiRequest);
    assert.equal(firstPage.source, "api");
    assert.deepEqual(calls[0], { path: "/products", cache: "no-store" });
    assert.deepEqual(calls[1].query, { page: "2" });
  });
});

test("product catalog does not replace an API failure with fixture data", async () => {
  await withApiDataSource(async () => {
    const failure = new Error("upstream unavailable");
    const apiRequest = stubTransport(() => {
      throw failure;
    });
    await assert.rejects(getProductCatalog({}, apiRequest), (error) => error === failure);
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
  assert.match(page, /ProductCollectionPagination/);
  assert.equal((page.match(/component="h1"/g) ?? []).length, 1);
  assert.doesNotMatch(page, /["']use client["']|notFound\(\)|<main|component="main"|href="#"|\/cart|\/checkout|\/payment|wishlist|Buy now/);
  assert.match(grid, /ProductCard/);
  assert.match(card, /var\(--media-product-card\)/);
  assert.match(navigation, /"\/products"/);
  assert.match(proxy, /"\/categories\/:path\*"/);
  assert.match(proxy, /"\/products\/:slug"/);
});

test("product listing delegates URL query parsing and pagination preservation to the shared CAT-001 helpers", () => {
  const page = read("app/products/page.tsx");
  const query = read("lib/catalog/filters.ts");
  assert.match(page, /parseProductCollectionQuery/);
  assert.match(page, /ProductCollectionPagination/);
  assert.match(query, /isPageOne/);
  assert.match(query, /productCollectionHref/);
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
  assert.match(page, /error\.kind === "api"/);
  assert.match(page, /RESOURCE_NOT_FOUND/);
  assert.match(page, /PRODUCT_NOT_FOUND/);
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
