import assert from "node:assert/strict";
import test from "node:test";
import { HOMEPAGE_PRODUCT_DETAIL_FIXTURES } from "@/lib/homepage/fixtures";
import {
  buildBreadcrumbStructuredData,
  buildProductStructuredData,
  buildSiteStructuredData,
  serializeJsonLd,
  type JsonLdNode,
} from "@/lib/seo/structured-data";
import { read } from "../test-utils/frontend-test-helpers";

const TEST_ORIGIN = "https://sl-furnitures.test";
const HOSTILE = "</script><script>alert(1)</script>";

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

function parseNode(node: JsonLdNode): Record<string, unknown> {
  return JSON.parse(serializeJsonLd(node)) as Record<string, unknown>;
}

test("serialized JSON-LD cannot break out of the script element and round-trips hostile content", () => {
  const serialized = serializeJsonLd({ description: HOSTILE });
  assert.doesNotMatch(serialized, /</);
  assert.doesNotMatch(serialized, />/);
  assert.doesNotMatch(serialized, /<\/script>/i);
  assert.doesNotMatch(serialized, /<script>/i);
  assert.equal((JSON.parse(serialized) as { description: string }).description, HOSTILE);

  for (const value of ["<", ">", "&", "\"quoted\"", "\u2028\u2029", "café — 椅子", HOSTILE]) {
    const roundTrip = JSON.parse(serializeJsonLd({ value })) as { value: string };
    assert.equal(roundTrip.value, value);
  }
});

test("homepage emits a single truthful WebSite and Organization graph", () => {
  withSiteUrl(TEST_ORIGIN, () => {
    const graph = buildSiteStructuredData();
    assert.ok(graph);
    const parsed = parseNode(graph) as { "@context": string; "@graph": readonly Record<string, unknown>[] };
    assert.equal(parsed["@context"], "https://schema.org");

    const website = parsed["@graph"].find((entry) => entry["@type"] === "WebSite");
    const organization = parsed["@graph"].find((entry) => entry["@type"] === "Organization");
    assert.ok(website);
    assert.ok(organization);
    assert.equal(website["@id"], `${TEST_ORIGIN}/#website`);
    assert.equal(website.url, `${TEST_ORIGIN}/`);
    assert.equal(website.name, "SL Furnitures");
    assert.equal(organization["@id"], `${TEST_ORIGIN}/#organization`);
    assert.equal(organization.name, "SL Furnitures");
    assert.equal(organization.url, `${TEST_ORIGIN}/`);

    const serialized = serializeJsonLd(graph);
    assert.doesNotMatch(serialized, /SearchAction|API_BASE_URL|localhost|127\.0\.0\.1/);
    assert.equal(Object.keys(organization).sort().join(","), "@id,@type,name,url");
  });
});

test("site graph is omitted when the website origin is not configured", () => {
  withSiteUrl(undefined, () => {
    assert.equal(buildSiteStructuredData(), undefined);
    assert.equal(buildBreadcrumbStructuredData([{ name: "Home", path: "/" }]), undefined);
  });
});

test("product structured data maps CAT-002 facts without inventing commercial or identity data", () => {
  const product = HOMEPAGE_PRODUCT_DETAIL_FIXTURES["fixture-open-frame-armchair"];
  withSiteUrl(TEST_ORIGIN, () => {
    const node = buildProductStructuredData(product);
    assert.ok(node);
    const parsed = parseNode(node);
    const canonical = `${TEST_ORIGIN}/products/${product.slug}`;

    assert.equal(parsed["@context"], "https://schema.org");
    assert.equal(parsed["@type"], "Product");
    assert.equal(parsed["@id"], `${canonical}#product`);
    assert.equal(parsed.name, product.name);
    assert.equal(parsed.url, canonical);
    assert.equal(parsed.description, product.description);
    assert.deepEqual(parsed.image, [`${TEST_ORIGIN}${product.images[0].url}`]);
    assert.equal(parsed.category, product.category.name);

    assert.doesNotMatch(JSON.stringify(parsed), new RegExp(product.id));
    for (const forbidden of ["offers", "brand", "sku", "gtin", "gtin8", "gtin12", "gtin13", "gtin14", "mpn", "aggregateRating", "review", "price", "priceCurrency", "priceValidUntil", "shippingDetails", "hasMerchantReturnPolicy"]) {
      assert.equal(parsed[forbidden], undefined, `product JSON-LD must not invent ${forbidden}`);
    }
  });
});

test("product structured data omits absent optional fields instead of emitting null values", () => {
  const product = { ...HOMEPAGE_PRODUCT_DETAIL_FIXTURES["fixture-soft-two-seat-sofa"], description: null, images: [] };
  withSiteUrl(TEST_ORIGIN, () => {
    const node = buildProductStructuredData(product);
    assert.ok(node);
    const parsed = parseNode(node);
    assert.equal(parsed.description, undefined);
    assert.equal(parsed.image, undefined);
    assert.doesNotMatch(serializeJsonLd(node), /null|"brand"|"sku"|"gtin"|"offers"/);
  });
});

test("hostile product descriptions are safely serialized into JSON-LD", () => {
  const product = { ...HOMEPAGE_PRODUCT_DETAIL_FIXTURES["fixture-barrel-chair"], description: HOSTILE };
  withSiteUrl(TEST_ORIGIN, () => {
    const node = buildProductStructuredData(product);
    assert.ok(node);
    const serialized = serializeJsonLd(node);
    assert.doesNotMatch(serialized, /<\/script>/i);
    assert.equal((JSON.parse(serialized) as { description: string }).description, HOSTILE);
  });
});

test("breadcrumbs mirror the visible hierarchy with absolute canonical URLs and backend slugs", () => {
  const product = HOMEPAGE_PRODUCT_DETAIL_FIXTURES["fixture-open-frame-armchair"];
  withSiteUrl(TEST_ORIGIN, () => {
    const productCrumbs = parseNode(buildBreadcrumbStructuredData([
      { name: "Home", path: "/" },
      { name: "Furniture", path: "/products" },
      { name: product.category.name, path: `/categories/${product.category.slug}` },
      { name: product.name, path: `/products/${product.slug}` },
    ]) as JsonLdNode) as { "@type": string; itemListElement: readonly Record<string, unknown>[] };

    assert.equal(productCrumbs["@type"], "BreadcrumbList");
    assert.deepEqual(productCrumbs.itemListElement.map((item) => item.position), [1, 2, 3, 4]);
    assert.deepEqual(productCrumbs.itemListElement.map((item) => item.name), ["Home", "Furniture", product.category.name, product.name]);
    assert.equal(productCrumbs.itemListElement[2].item, `${TEST_ORIGIN}/categories/${product.category.slug}`);
    assert.equal(productCrumbs.itemListElement[3].item, `${TEST_ORIGIN}/products/${product.slug}`);

    const categoryCrumbs = buildBreadcrumbStructuredData([
      { name: "Home", path: "/" },
      { name: "Living Room", path: "/categories/living-room" },
    ]);
    assert.ok(categoryCrumbs);
    const categoryParsed = parseNode(categoryCrumbs) as { itemListElement: readonly Record<string, unknown>[] };
    assert.deepEqual(categoryParsed.itemListElement.map((item) => item.position), [1, 2]);
    assert.doesNotMatch(serializeJsonLd(categoryCrumbs), /API_BASE_URL|localhost|\/api\/v1/);
  });
});

test("structured data stays off listing, category, and search collections and reuses Phase 14.7 boundaries", () => {
  const productsPage = read("app/products/page.tsx");
  const searchPage = read("app/search/page.tsx");
  const categoryPage = read("app/categories/[slug]/page.tsx");
  const jsonLd = read("components/seo/json-ld.tsx");
  const structuredData = read("lib/seo/structured-data.ts");
  const site = read("lib/seo/site.ts");

  assert.doesNotMatch(productsPage, /JsonLd|StructuredData|application\/ld/);
  assert.doesNotMatch(searchPage, /JsonLd|StructuredData|application\/ld/);
  assert.doesNotMatch(categoryPage, /buildProductStructuredData|"@type": "Product"/);
  assert.doesNotMatch(jsonLd, /use client|useEffect|useState/);
  assert.match(structuredData, /from "\.\/site"/);
  assert.doesNotMatch(structuredData, /API_BASE_URL|CATALOG_MEDIA_BASE_URL|localhost|react-helmet|next-seo/);
  assert.doesNotMatch(structuredData, /process\.env/);
  assert.match(site, /process\.env\.SITE_URL/);
});
