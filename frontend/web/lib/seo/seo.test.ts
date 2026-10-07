import assert from "node:assert/strict";
import { existsSync } from "node:fs";
import test from "node:test";
import type { Metadata } from "next";
import { HOMEPAGE_CATEGORY_FIXTURES, HOMEPAGE_PRODUCT_DETAIL_FIXTURES } from "@/lib/homepage/fixtures";
import {
  buildCategoryMetadata,
  buildHomeMetadata,
  buildProductMetadata,
  buildProductsMetadata,
  buildSearchMetadata,
} from "@/lib/seo/catalog-metadata";
import { NOINDEX_FOLLOW } from "@/lib/seo/metadata";
import { buildCanonicalPath, buildCanonicalUrl, getSiteOrigin, SiteUrlError } from "@/lib/seo/site";
import { read } from "../test-utils/frontend-test-helpers";

const TEST_ORIGIN = "https://sl-furnitures.test";

type SerializedMetadata = {
  title?: string;
  description?: string;
  robots?: { index?: boolean; follow?: boolean };
  alternates?: { canonical?: string };
  openGraph?: { title?: string; description?: string; siteName?: string; type?: string; url?: string; images?: readonly { url?: string; alt?: string; width?: number; height?: number }[] };
  twitter?: { card?: string; title?: string; description?: string; images?: readonly string[] };
};

function serialize(metadata: Metadata): SerializedMetadata {
  return JSON.parse(JSON.stringify(metadata)) as SerializedMetadata;
}

function withSiteUrl<T>(value: string | undefined, callback: () => T): T {
  const previous = process.env.SITE_URL;
  if (value === undefined) {
    delete process.env.SITE_URL;
  } else {
    process.env.SITE_URL = value;
  }
  try {
    return callback();
  } finally {
    if (previous === undefined) {
      delete process.env.SITE_URL;
    } else {
      process.env.SITE_URL = previous;
    }
  }
}

test("site origin comes only from SITE_URL and rejects unsafe or malformed values", () => {
  withSiteUrl(undefined, () => assert.equal(getSiteOrigin(), undefined));
  withSiteUrl(TEST_ORIGIN, () => assert.equal(getSiteOrigin(), TEST_ORIGIN));
  withSiteUrl("http://localhost:3000", () => assert.equal(getSiteOrigin(), "http://localhost:3000"));
  for (const invalid of ["slfurnitures.com", "mailto:seo@example.com", "https://example.com/path", "https://user:pass@example.com", "http://example.com"]) {
    withSiteUrl(invalid, () => assert.throws(() => getSiteOrigin(), SiteUrlError));
  }
});

test("production rejects a localhost website origin", () => {
  const env = process.env as Record<string, string | undefined>;
  const previous = env.NODE_ENV;
  env.NODE_ENV = "production";
  try {
    withSiteUrl("http://localhost:3000", () => assert.throws(() => getSiteOrigin(), SiteUrlError));
    withSiteUrl("http://127.0.0.1:8000", () => assert.throws(() => getSiteOrigin(), SiteUrlError));
    withSiteUrl(TEST_ORIGIN, () => assert.equal(getSiteOrigin(), TEST_ORIGIN));
  } finally {
    if (previous === undefined) {
      delete env.NODE_ENV;
    } else {
      env.NODE_ENV = previous;
    }
  }
});

test("canonical paths are slashless for non-root routes and preserve meaningful query state", () => {
  assert.equal(buildCanonicalPath("/"), "/");
  assert.equal(buildCanonicalPath("/products"), "/products");
  assert.equal(buildCanonicalPath("/products/"), "/products");
  assert.equal(buildCanonicalPath("products"), "/products");
  assert.equal(buildCanonicalPath("/products?page=2"), "/products?page=2");
  withSiteUrl(undefined, () => assert.equal(buildCanonicalUrl("/products"), undefined));
  withSiteUrl(TEST_ORIGIN, () => assert.equal(buildCanonicalUrl("/products?page=2"), `${TEST_ORIGIN}/products?page=2`));
  withSiteUrl(TEST_ORIGIN, () => assert.equal(buildCanonicalUrl("/"), `${TEST_ORIGIN}/`));
});

test("homepage metadata is unique, factual, and free of fixture social imagery", () => {
  withSiteUrl(TEST_ORIGIN, () => {
    const metadata = serialize(buildHomeMetadata());
    assert.equal(metadata.title, "Furniture for the way you live");
    assert.equal(metadata.robots, undefined);
    assert.equal(metadata.alternates?.canonical, `${TEST_ORIGIN}/`);
    assert.equal(metadata.openGraph?.siteName, "SL Furnitures");
    assert.equal(metadata.openGraph?.type, "website");
    assert.equal(metadata.openGraph?.url, `${TEST_ORIGIN}/`);
    assert.equal(metadata.openGraph?.images, undefined);
    assert.doesNotMatch(JSON.stringify(metadata), /fixtures/);
  });
});

test("product collection metadata keeps the base indexable while faceting and sorting stay noindex", () => {
  withSiteUrl(TEST_ORIGIN, () => {
    const base = serialize(buildProductsMetadata({}));
    assert.equal(base.alternates?.canonical, `${TEST_ORIGIN}/products`);
    assert.equal(base.robots, undefined);

    const pageOne = serialize(buildProductsMetadata({ page: "1" }));
    assert.equal(pageOne.alternates?.canonical, `${TEST_ORIGIN}/products`);

    const paginated = serialize(buildProductsMetadata({ page: "2" }));
    assert.equal(paginated.alternates?.canonical, `${TEST_ORIGIN}/products?page=2`);
    assert.equal(paginated.robots, undefined);

    const faceted = serialize(buildProductsMetadata({ category: "living-room" }));
    assert.equal(faceted.alternates?.canonical, `${TEST_ORIGIN}/products`);
    assert.deepEqual(faceted.robots, { index: false, follow: true });

    const filteredPaged = serialize(buildProductsMetadata({ product_type: "MADE_TO_ORDER", page: "3" }));
    assert.equal(filteredPaged.alternates?.canonical, `${TEST_ORIGIN}/products`);
    assert.deepEqual(filteredPaged.robots, { index: false, follow: true });

    const searched = serialize(buildProductsMetadata({ search: "chair" }));
    assert.equal(searched.alternates?.canonical, `${TEST_ORIGIN}/products`);
    assert.deepEqual(searched.robots, { index: false, follow: true });

    const searchedPaged = serialize(buildProductsMetadata({ search: "chair", page: "2" }));
    assert.equal(searchedPaged.alternates?.canonical, `${TEST_ORIGIN}/products`);
    assert.deepEqual(searchedPaged.robots, { index: false, follow: true });

    for (const query of [{ availability: "available" }, { min_price: "100" }, { max_price: "200" }, { sort: "price" }, { sort_direction: "asc" }]) {
      assert.deepEqual(serialize(buildProductsMetadata(query)).robots, { index: false, follow: true });
    }
  });
});

test("search metadata stays noindex, follow and bounds untrusted search terms", () => {
  withSiteUrl(TEST_ORIGIN, () => {
    const entry = serialize(buildSearchMetadata({}));
    assert.equal(entry.title, "Search furniture");
    assert.equal(entry.alternates?.canonical, `${TEST_ORIGIN}/search`);
    assert.deepEqual(entry.robots, { index: false, follow: true });

    const term = serialize(buildSearchMetadata({ search: "chair" }));
    assert.match(term.title ?? "", /Search results for/);
    assert.match(term.title ?? "", /chair/);

    const script = serialize(buildSearchMetadata({ search: "<script>alert(1)</script>" }));
    assert.equal(script.title, "Search results for \u201C<script>alert(1)</script>\u201D");

    const long = serialize(buildSearchMetadata({ search: "a".repeat(200) }));
    assert.ok((long.title ?? "").length < 100);
  });
});

test("product metadata derives from CAT-002 fields and uses the backend slug", () => {
  const product = HOMEPAGE_PRODUCT_DETAIL_FIXTURES["fixture-open-frame-armchair"];
  withSiteUrl(TEST_ORIGIN, () => {
    const metadata = serialize(buildProductMetadata(product));
    assert.equal(metadata.title, product.name);
    assert.equal(metadata.alternates?.canonical, `${TEST_ORIGIN}/products/${product.slug}`);
    assert.equal(metadata.openGraph?.url, `${TEST_ORIGIN}/products/${product.slug}`);
    assert.equal(metadata.openGraph?.type, "website");
    assert.equal(metadata.openGraph?.images?.[0]?.url, product.images[0].url);
    assert.equal(metadata.openGraph?.images?.[0]?.alt, product.images[0].alt_text);
    assert.equal(metadata.openGraph?.images?.[0]?.width, undefined);
    assert.equal(metadata.openGraph?.images?.[0]?.height, undefined);
    assert.equal(metadata.twitter?.card, "summary_large_image");
    assert.deepEqual(metadata.twitter?.images, [product.images[0].url]);
  });
});

test("product metadata falls back to a factual description without inventing claims or media", () => {
  const product = { ...HOMEPAGE_PRODUCT_DETAIL_FIXTURES["fixture-soft-two-seat-sofa"], description: null, images: [] };
  withSiteUrl(TEST_ORIGIN, () => {
    const metadata = serialize(buildProductMetadata(product));
    assert.match(metadata.description ?? "", new RegExp(product.name));
    assert.equal(metadata.openGraph?.images, undefined);
    assert.equal(metadata.twitter?.card, "summary");
    assert.doesNotMatch(JSON.stringify(metadata), /free delivery|best prices|award|luxury|premium|sustainable|shipping/i);
  });
});

test("product and category metadata omit relative social images when SITE_URL is unavailable", () => {
  const product = HOMEPAGE_PRODUCT_DETAIL_FIXTURES["fixture-open-frame-armchair"];
  const category = { ...HOMEPAGE_CATEGORY_FIXTURES[0], description: null };
  withSiteUrl(undefined, () => {
    const productMetadata = serialize(buildProductMetadata(product));
    assert.equal(productMetadata.alternates?.canonical, undefined);
    assert.equal(productMetadata.openGraph?.images, undefined);
    assert.equal(productMetadata.twitter?.images, undefined);
    assert.equal(productMetadata.twitter?.card, "summary");

    const categoryMetadata = serialize(buildCategoryMetadata(category, 1));
    assert.equal(categoryMetadata.alternates?.canonical, undefined);
    assert.equal(categoryMetadata.openGraph?.images, undefined);
    assert.equal(categoryMetadata.twitter?.images, undefined);
  });
});

test("category metadata derives from CAT-004, uses the backend slug, and self-canonicalizes pagination", () => {
  const category = { ...HOMEPAGE_CATEGORY_FIXTURES[0], description: "Furniture for living spaces." };
  withSiteUrl(TEST_ORIGIN, () => {
    const metadata = serialize(buildCategoryMetadata(category, 1));
    assert.equal(metadata.title, category.name);
    assert.equal(metadata.alternates?.canonical, `${TEST_ORIGIN}/categories/${category.slug}`);
    assert.equal(metadata.openGraph?.images?.[0]?.url, category.image?.url);
    assert.equal(metadata.openGraph?.images?.[0]?.alt, category.name);

    const paged = serialize(buildCategoryMetadata(category, 2));
    assert.equal(paged.alternates?.canonical, `${TEST_ORIGIN}/categories/${category.slug}?page=2`);

    const fallback = serialize(buildCategoryMetadata({ ...category, description: null }, 1));
    assert.equal(fallback.description, `${category.name} furniture from SL Furnitures.`);
  });
});

test("metadata layer stays server-only and respects phase boundaries", () => {
  const site = read("lib/seo/site.ts");
  const catalogMetadata = read("lib/seo/catalog-metadata.ts");
  const layout = read("app/layout.tsx");
  const proxy = read("proxy.ts");

  assert.match(site, /process\.env\.SITE_URL/);
  assert.doesNotMatch(site, /API_BASE_URL|CATALOG_MEDIA_BASE_URL|NEXT_PUBLIC/);
  assert.match(layout, /template: `%s \| \$\{SITE_NAME\}`/);
  assert.doesNotMatch(layout, /Premium for less|Tanzania/);
  assert.doesNotMatch(catalogMetadata, /ld\+json|application\/ld|dangerouslySetInnerHTML|next\/head/);
  assert.doesNotMatch(proxy, /metadata|SITE_URL/);
  assert.equal(NOINDEX_FOLLOW.index, false);
  assert.equal(existsSync("app/sitemap.ts"), true);
  assert.equal(existsSync("app/robots.ts"), true);
});
