import assert from "node:assert/strict";
import { readdirSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import test from "node:test";
import { fileURLToPath } from "node:url";
import { createElement } from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { ProductCard } from "@/components/catalog/product-card";
import { HOMEPAGE_PRODUCT_FIXTURES } from "@/lib/homepage/fixtures";
import { CATEGORY_PRODUCT_IMAGE_SIZES, HERO_IMAGE_SIZES, PRODUCT_DETAIL_IMAGE_SIZES, PRODUCT_IMAGE_SIZES } from "@/lib/homepage/image-sizes";
import { read } from "../test-utils/frontend-test-helpers";

const webRoot = resolve(dirname(fileURLToPath(import.meta.url)), "../..");

function listSources(directory: string): string[] {
  return readdirSync(join(webRoot, directory), { withFileTypes: true }).flatMap((entry) => {
    const relative = join(directory, entry.name);
    if (entry.isDirectory()) {
      return listSources(relative);
    }
    return /\.(ts|tsx)$/.test(entry.name) ? [relative] : [];
  });
}

const APP_AND_COMPONENT_SOURCES = [...listSources("app"), ...listSources("components")];
const EXPECTED_CLIENT_COMPONENTS = [
  "app/error.tsx",
  "app/providers.tsx",
  "components/catalog/price-filter-inputs.tsx",
  "components/catalog/sort-filter-select.tsx",
  "components/furniture-requests/furniture-request-form.tsx",
  "components/layout/auth-navigation.tsx",
  "components/layout/mobile-navigation.tsx",
];

test("catalog pages remain server-first and client boundaries stay narrow", () => {
  for (const page of ["app/page.tsx", "app/products/page.tsx", "app/products/[slug]/page.tsx", "app/categories/[slug]/page.tsx", "app/search/page.tsx"]) {
    assert.doesNotMatch(read(page), /["']use client["']/, `${page} must remain a Server Component`);
  }

  const clientFiles = APP_AND_COMPONENT_SOURCES.filter((file) => /["']use client["']/.test(read(file))).sort();
  assert.deepEqual(clientFiles, EXPECTED_CLIENT_COMPONENTS);
});

test("catalog photography uses next/image with no raw image tags or deprecated priority prop", () => {
  const combined = APP_AND_COMPONENT_SOURCES.map((file) => read(file)).join("\n");
  assert.doesNotMatch(combined, /<img[\s>]/);
  assert.doesNotMatch(combined, /\bpriority=/);
  assert.match(read("app/page.tsx"), /preload/);
  assert.match(read("app/products/[slug]/page.tsx"), /preload=\{preload\}/);
});

test("product grid image sizes match the real responsive column behaviour", () => {
  assert.equal(PRODUCT_IMAGE_SIZES, CATEGORY_PRODUCT_IMAGE_SIZES);
  assert.match(PRODUCT_IMAGE_SIZES, /max-width: 960px/);
  assert.match(PRODUCT_IMAGE_SIZES, /\/ 2\)/);
  assert.match(PRODUCT_IMAGE_SIZES, /\/ 3\)/);
  assert.match(HERO_IMAGE_SIZES, /2 \/ 3/);
  assert.match(PRODUCT_DETAIL_IMAGE_SIZES, /\/ 2\)/);

  const grid = read("components/catalog/product-grid.tsx");
  assert.match(grid, /repeat\(2, minmax\(0, 1fr\)\)/);
  assert.match(grid, /repeat\(3, minmax\(0, 1fr\)\)/);
  assert.match(grid, /sizes=\{sizes\}/);
});

test("product cards reserve stable geometry and render no media request when the image is missing", () => {
  assert.match(read("components/catalog/product-card.tsx"), /--media-product-card/);

  const missing = renderToStaticMarkup(createElement(ProductCard, { product: { ...HOMEPAGE_PRODUCT_FIXTURES[0], primary_image: null }, sizes: "33vw" }));
  assert.match(missing, /Photograph to follow/);
  assert.doesNotMatch(missing, /<img/);
});

test("PDP loads only the lead image eagerly and never duplicates it in the gallery", () => {
  const page = read("app/products/[slug]/page.tsx");
  assert.match(page, /secondaryImages = product\.images\.filter\(\(image\) => image\.id !== leadImage\?\.id\)/);
  assert.match(page, /<ProductMedia image=\{leadImage\} productName=\{product\.name\} preload \/>/);
  assert.match(page, /secondaryImages\.map\(\(image\) => <ProductMedia key=\{image\.id\} image=\{image\} productName=\{product\.name\} \/>\)/);
});

test("collection cards reuse lightweight CAT-001 summaries without per-card detail fetches", () => {
  const collectionSources = [read("components/catalog/product-card.tsx"), read("components/catalog/product-grid.tsx")].join("\n");
  assert.doesNotMatch(collectionSources, /getProductDetail|apiRequest|fetch\(|cache: "no-store"/);
});

test("detail metadata/page resolution keeps request-local deduplication and structured data reuses it", () => {
  assert.match(read("app/products/[slug]/page.tsx"), /cache\(loadProductDetail\)/);
  assert.match(read("app/categories/[slug]/page.tsx"), /cache\(/);
  assert.doesNotMatch(read("lib/seo/structured-data.ts"), /apiRequest|fetch\(/);
});

test("remote image origins stay narrowly allow-listed from the existing media configuration", () => {
  const config = read("next.config.ts");
  assert.match(config, /CATALOG_MEDIA_BASE_URL/);
  assert.doesNotMatch(config, /hostname:\s*["']\*["']|"\*\*"|NEXT_PUBLIC_/);
});

test("fonts load locally without a blocking remote request", () => {
  const layout = read("app/layout.tsx");
  assert.match(layout, /next\/font\/local/);
  assert.doesNotMatch(layout, /next\/font\/google|fonts\.googleapis\.com|fonts\.gstatic\.com/);
});
