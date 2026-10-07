import assert from "node:assert/strict";
import test from "node:test";
import { renderToStaticMarkup } from "react-dom/server";
import SearchPage from "@/app/search/page";
import { isSiteRouteImplemented } from "@/components/layout/site-navigation";
import type { ApiRequestOptions } from "@/lib/api/client";
import { getProductCatalog } from "@/lib/products/catalog";
import { read, stubTransport, withApiDataSource } from "../test-utils/frontend-test-helpers";

test("search sends the meaningful term to CAT-001 and preserves API failures", async () => {
  await withApiDataSource(async () => {
    const calls: ApiRequestOptions[] = [];
    const apiRequest = stubTransport((request) => {
      calls.push(request);
      return { data: [], meta: { pagination: { current_page: 1, per_page: 20, total: 0, last_page: 1, has_next: false, has_previous: false } } };
    });
    await getProductCatalog(2, apiRequest, { search: "chair" });
    assert.deepEqual(calls[0], { path: "/products", cache: "no-store", query: { search: "chair", page: 2 } });

    const failure = new Error("upstream unavailable");
    await assert.rejects(getProductCatalog(1, stubTransport(() => { throw failure; }), { search: "chair" }), (error) => error === failure);
  });
});

test("search route is server-rendered, uses a native GET form, and retains search pagination", () => {
  const page = read("app/search/page.tsx");
  const proxy = read("proxy.ts");
  const affordance = read("components/layout/search-affordance.tsx");

  assert.match(page, /getProductCatalog/);
  assert.match(page, /ProductGrid/);
  assert.match(page, /component="form"/);
  assert.match(page, /action="\/search"/);
  assert.match(page, /method="get"/);
  assert.match(page, /htmlInput: \{ name: "search" \}/);
  assert.match(page, /new URLSearchParams/);
  assert.equal((page.match(/component="h1"/g) ?? []).length, 1);
  assert.doesNotMatch(page, /["']use client["']|useState|useEffect|useRouter|router\.push|window\.location|dangerouslySetInnerHTML|\/api\/v1\/search|fuse|lunr|algolia/);
  assert.doesNotMatch(proxy, /search/);
  assert.match(affordance, /href="\/search"/);
  assert.match(affordance, />\s*Search furniture\s*</);
  assert.equal(isSiteRouteImplemented("/search"), true);
});

test("search distinguishes entry, populated, empty, and HTML-like query states without JavaScript", async () => {
  const previous = process.env.HOMEPAGE_DATA_SOURCE;
  process.env.HOMEPAGE_DATA_SOURCE = "fixtures";

  try {
    const entry = renderToStaticMarkup(await SearchPage({ searchParams: Promise.resolve({}) }));
    const populated = renderToStaticMarkup(await SearchPage({ searchParams: Promise.resolve({ search: "chair" }) }));
    const empty = renderToStaticMarkup(await SearchPage({ searchParams: Promise.resolve({ search: "no-match" }) }));
    const htmlLike = renderToStaticMarkup(await SearchPage({ searchParams: Promise.resolve({ search: "<script>alert(1)</script>" }) }));

    assert.match(entry, /Enter a search term/);
    assert.match(populated, /lounge chair/);
    assert.match(populated, /Design preview/);
    assert.match(empty, /No furniture found for/);
    assert.match(empty, /Design preview/);
    assert.match(htmlLike, /&lt;script&gt;alert\(1\)&lt;\/script&gt;/);
    assert.doesNotMatch(htmlLike, /<script>alert\(1\)<\/script>/);
  } finally {
    if (previous === undefined) delete process.env.HOMEPAGE_DATA_SOURCE;
    else process.env.HOMEPAGE_DATA_SOURCE = previous;
  }
});
