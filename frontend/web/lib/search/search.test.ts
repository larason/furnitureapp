import assert from "node:assert/strict";
import test from "node:test";
import { renderToStaticMarkup } from "react-dom/server";
import SearchPage from "@/app/search/page";
import { isSiteRouteImplemented } from "@/components/layout/site-navigation";
import { ApiError, type ApiRequestOptions } from "@/lib/api/client";
import { isSearchValidationError, SEARCH_QUERY_MAX_LENGTH } from "@/lib/catalog/filters";
import { getProductCatalog } from "@/lib/products/catalog";
import { read, stubTransport, withApiDataSource } from "../test-utils/frontend-test-helpers";

test("search sends the meaningful term to CAT-001 and preserves API failures", async () => {
  await withApiDataSource(async () => {
    const calls: ApiRequestOptions[] = [];
    const apiRequest = stubTransport((request) => {
      calls.push(request);
      return { data: [], meta: { pagination: { current_page: 1, per_page: 20, total: 0, last_page: 1, has_next: false, has_previous: false } } };
    });
    await getProductCatalog({ search: "chair", page: "2" }, apiRequest);
    assert.deepEqual(calls[0], { path: "/products", cache: "no-store", query: { search: "chair", page: "2" } });

    const failure = new Error("upstream unavailable");
    await assert.rejects(getProductCatalog({ search: "chair" }, stubTransport(() => { throw failure; })), (error) => error === failure);
  });
});

test("search length limit matches the API contract and only 422 search validation is treated as expected", () => {
  assert.equal(SEARCH_QUERY_MAX_LENGTH, 100);
  const searchValidation = new ApiError(422, [{ code: "INVALID_VALUE", message: "The search field must not be greater than 100 characters.", field: "search" }], "req_1");
  const otherFieldValidation = new ApiError(422, [{ code: "INVALID_VALUE", message: "The category field is invalid.", field: "category" }], "req_2");
  const serverError = new ApiError(500, [{ code: "SERVER_ERROR", message: "Unexpected error." }], "req_3");

  assert.equal(isSearchValidationError(searchValidation), true);
  assert.equal(isSearchValidationError(otherFieldValidation), false);
  assert.equal(isSearchValidationError(serverError), false);
  assert.equal(isSearchValidationError(new Error("boom")), false);
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
  assert.match(page, /htmlInput: \{ name: "search", maxLength: SEARCH_QUERY_MAX_LENGTH \}/);
  assert.match(page, /isSearchValidationError/);
  assert.match(page, /ProductCollectionPagination/);
  assert.match(read("lib/catalog/filters.ts"), /new URLSearchParams/);
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
    assert.match(empty, /No furniture matches these filters/);
    assert.match(empty, /Design preview/);
    assert.match(htmlLike, /&lt;script&gt;alert\(1\)&lt;\/script&gt;/);
    assert.doesNotMatch(htmlLike, /<script>alert\(1\)<\/script>/);
  } finally {
    if (previous === undefined) delete process.env.HOMEPAGE_DATA_SOURCE;
    else process.env.HOMEPAGE_DATA_SOURCE = previous;
  }
});
