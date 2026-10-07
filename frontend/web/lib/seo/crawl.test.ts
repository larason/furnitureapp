import assert from "node:assert/strict";
import test from "node:test";
import type { MetadataRoute } from "next";
import type { ApiRequestOptions, ApiSuccess } from "@/lib/api/client";
import type { CategorySummary, ProductSummary } from "@/lib/catalog/types";
import { buildRobotsRules, buildSitemapEntries } from "@/lib/seo/crawl";
import { SiteUrlError } from "@/lib/seo/site";
import { read, stubTransport, withApiDataSource } from "../test-utils/frontend-test-helpers";

const TEST_ORIGIN = "https://sl-furnitures.test";

function product(slug: string): ProductSummary {
  return { id: `prod_${slug}`, slug, name: slug, price: { amount: 100, currency: "TZS" }, product_type: "MADE_TO_ORDER", availability: "available", stock_indicator: "MADE_TO_ORDER", primary_image: null };
}

function category(slug: string): CategorySummary {
  return { id: `cat_${slug}`, slug, name: slug, image: null };
}

function page<T>(data: readonly T[], currentPage: number, lastPage: number): ApiSuccess<readonly T[]> {
  return { data, meta: { pagination: { current_page: currentPage, per_page: 100, total: data.length, last_page: lastPage, has_next: currentPage < lastPage, has_previous: currentPage > 1 } } };
}

async function withSiteUrl<T>(value: string | undefined, callback: () => Promise<T> | T): Promise<T> {
  const previous = process.env.SITE_URL;
  if (value === undefined) {
    delete process.env.SITE_URL;
  } else {
    process.env.SITE_URL = value;
  }
  try {
    return await callback();
  } finally {
    if (previous === undefined) {
      delete process.env.SITE_URL;
    } else {
      process.env.SITE_URL = previous;
    }
  }
}

function firstRule(file: MetadataRoute.Robots) {
  return Array.isArray(file.rules) ? file.rules[0] : file.rules;
}

test("sitemap lists canonical static, category, and product URLs with no query state or invented hints", async () => {
  await withApiDataSource(() => withSiteUrl(TEST_ORIGIN, async () => {
    const apiRequest = stubTransport((request) => (
      request.path === "/categories"
        ? page([category("bedroom"), category("living-room")], 1, 1)
        : page([product("modern-sofa"), product("oak-desk")], 1, 1)
    ));

    const entries = await buildSitemapEntries(apiRequest);
    assert.deepEqual(entries.map((entry) => entry.url), [
      `${TEST_ORIGIN}/`,
      `${TEST_ORIGIN}/products`,
      `${TEST_ORIGIN}/categories/bedroom`,
      `${TEST_ORIGIN}/categories/living-room`,
      `${TEST_ORIGIN}/products/modern-sofa`,
      `${TEST_ORIGIN}/products/oak-desk`,
    ]);
    assert.ok(entries.every((entry) => entry.url.startsWith(`${TEST_ORIGIN}/`) && !entry.url.includes("?") && !entry.url.includes("prod_")));
    assert.ok(entries.every((entry) => entry.lastModified === undefined && entry.changeFrequency === undefined && entry.priority === undefined));
    assert.ok(!entries.some((entry) => entry.url.includes("/search")));
  }));
});

test("sitemap traverses every CAT-001 page with per_page=100 and authoritative metadata", async () => {
  await withApiDataSource(() => withSiteUrl(TEST_ORIGIN, async () => {
    const requests: ApiRequestOptions[] = [];
    const apiRequest = stubTransport((request) => {
      requests.push(request);
      if (request.path === "/categories") {
        return page([], 1, 1);
      }
      const pageNumber = Number((request.query as Record<string, unknown>).page);
      return pageNumber === 2 ? page([product("third")], 2, 2) : page([product("first"), product("second")], 1, 2);
    });

    const entries = await buildSitemapEntries(apiRequest);
    const productRequests = requests.filter((request) => request.path === "/products");
    assert.deepEqual(productRequests.map((request) => request.query), [
      { per_page: 100, page: 1 },
      { per_page: 100, page: 2 },
    ]);
    assert.deepEqual(entries.slice(-3).map((entry) => entry.url), [
      `${TEST_ORIGIN}/products/first`,
      `${TEST_ORIGIN}/products/second`,
      `${TEST_ORIGIN}/products/third`,
    ]);
  }));
});

test("sitemap remains valid with an empty catalog and deduplicates repeated slugs defensively", async () => {
  await withApiDataSource(() => withSiteUrl(TEST_ORIGIN, async () => {
    const empty = await buildSitemapEntries(stubTransport((request) => (request.path === "/categories" ? page([], 1, 1) : page([], 1, 1))));
    assert.deepEqual(empty.map((entry) => entry.url), [`${TEST_ORIGIN}/`, `${TEST_ORIGIN}/products`]);

    const deduped = await buildSitemapEntries(stubTransport((request) => (
      request.path === "/categories"
        ? page([category("living-room"), category("living-room")], 1, 1)
        : page([product("chair"), product("chair")], 1, 1)
    )));
    const urls = deduped.map((entry) => entry.url);
    assert.equal(urls.filter((url) => url === `${TEST_ORIGIN}/products/chair`).length, 1);
    assert.equal(urls.filter((url) => url === `${TEST_ORIGIN}/categories/living-room`).length, 1);
  }));
});

test("sitemap emits nothing fabricated when SITE_URL is absent or malformed", async () => {
  await withApiDataSource(async () => {
    await withSiteUrl(undefined, async () => {
      const entries = await buildSitemapEntries(stubTransport(() => page([product("chair")], 1, 1)));
      assert.deepEqual(entries, []);
    });

    await withSiteUrl("not-a-valid-origin", async () => {
      await assert.rejects(buildSitemapEntries(stubTransport(() => page([], 1, 1))), SiteUrlError);
    });
  });
});

test("sitemap propagates unexpected catalog failure instead of falling back to fixtures", async () => {
  await withApiDataSource(() => withSiteUrl(TEST_ORIGIN, async () => {
    const failure = new Error("upstream unavailable");
    await assert.rejects(
      buildSitemapEntries(stubTransport(() => {
        throw failure;
      })),
      (error) => error === failure,
    );
  }));
});

test("sitemap uses explicit fixture data only when fixture mode is requested", async () => {
  const previous = process.env.HOMEPAGE_DATA_SOURCE;
  process.env.HOMEPAGE_DATA_SOURCE = "fixtures";
  try {
    await withSiteUrl(TEST_ORIGIN, async () => {
      const entries = await buildSitemapEntries(stubTransport(() => {
        throw new Error("transport must not be used in fixture mode");
      }));
      assert.ok(entries.some((entry) => entry.url === `${TEST_ORIGIN}/products/fixture-open-frame-armchair`));
      assert.ok(entries.some((entry) => entry.url === `${TEST_ORIGIN}/categories/living-room`));
    });
  } finally {
    if (previous === undefined) {
      delete process.env.HOMEPAGE_DATA_SOURCE;
    } else {
      process.env.HOMEPAGE_DATA_SOURCE = previous;
    }
  }
});

test("robots allows public crawling, disallows search and facet parameters, and advertises the sitemap only with SITE_URL", async () => {
  await withSiteUrl(TEST_ORIGIN, () => {
    const rules = firstRule(buildRobotsRules());
    assert.equal(rules.userAgent, "*");
    assert.equal(rules.allow, "/");
    const disallow = Array.isArray(rules.disallow) ? rules.disallow : [rules.disallow];
    assert.ok(disallow.includes("/search"));
    for (const parameter of ["category", "product_type", "availability", "min_price", "max_price", "sort", "sort_direction"]) {
      assert.ok(disallow.includes(`/*?${parameter}=`));
      assert.ok(disallow.includes(`/*&${parameter}=`));
    }
    assert.ok(!disallow.some((rule) => rule === "/*?*" || rule?.includes("page=")));
    assert.equal(buildRobotsRules().sitemap, `${TEST_ORIGIN}/sitemap.xml`);
    assert.equal(buildRobotsRules().host, undefined);
    assert.equal(rules.crawlDelay, undefined);
  });

  await withSiteUrl(undefined, () => {
    const file = buildRobotsRules();
    assert.equal(file.sitemap, undefined);
    const rules = firstRule(file);
    assert.equal(rules.allow, "/");
    assert.ok(!JSON.stringify(file).includes("localhost"));
  });
});

test("metadata routes stay server-only and outside the detail-resource proxy preflight", () => {
  const sitemap = read("app/sitemap.ts");
  const robots = read("app/robots.ts");
  const crawl = read("lib/seo/crawl.ts");
  const proxy = read("proxy.ts");

  assert.match(sitemap, /buildSitemapEntries/);
  assert.match(robots, /buildRobotsRules/);
  assert.doesNotMatch(sitemap + robots, /use client|fetch\(|apiRequest|API_BASE_URL/);
  assert.doesNotMatch(crawl, /use client|fetch\(|new Function|eval\(|node:vm/);
  assert.doesNotMatch(proxy, /sitemap|robots/);
});
