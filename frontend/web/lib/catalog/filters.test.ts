import assert from "node:assert/strict";
import test from "node:test";
import type { ApiRequestOptions } from "@/lib/api/client";
import { getCategoryFilterOptions } from "@/lib/category/options";
import {
  parseProductCollectionQuery,
  priceTzsToMinorUnits,
  productCollectionHref,
  serializeProductCollectionQuery,
  toFilterControlValues,
} from "./filters";
import { stubTransport, withApiDataSource } from "../test-utils/frontend-test-helpers";
import { read } from "../test-utils/frontend-test-helpers";

test("CAT-001 serializes every supported collection parameter and omits page one", () => {
  const query = parseProductCollectionQuery({
    search: "chair",
    category: "living-room",
    product_type: "MADE_TO_ORDER",
    availability: "available",
    min_price: "5000000",
    max_price: "200000000",
    sort: "price",
    sort_direction: "asc",
    page: "1",
  });

  assert.equal(
    serializeProductCollectionQuery(query),
    "search=chair&category=living-room&product_type=MADE_TO_ORDER&availability=available&min_price=5000000&max_price=200000000&sort=price&sort_direction=asc",
  );
});

test("CAT-001 preserves invalid and repeated URL values for Laravel validation while controls remain valid", () => {
  const query = parseProductCollectionQuery({ product_type: ["WRONG", "IN_STOCK"], availability: "yes", sort: "rating", min_price: "-1", max_price: "100" });

  assert.equal(serializeProductCollectionQuery(query), "product_type=WRONG&product_type=IN_STOCK&availability=yes&min_price=-1&max_price=100&sort=rating");
  assert.deepEqual(toFilterControlValues(query), { category: "", productType: "", availability: "", sort: "", sortDirection: "", minPriceTzs: "", maxPriceTzs: "1" });
});

test("price controls convert whole TZS to integer minor units without floating point arithmetic", () => {
  assert.equal(priceTzsToMinorUnits("125000"), "12500000");
  assert.equal(priceTzsToMinorUnits("0"), "0");
  assert.equal(priceTzsToMinorUnits("001"), "100");
  assert.equal(priceTzsToMinorUnits("1e3"), null);
  assert.equal(priceTzsToMinorUnits("-1"), null);
  assert.equal(priceTzsToMinorUnits("9007199254740992"), null);
});

test("pagination preserves filters and filter changes reset the page", () => {
  const query = parseProductCollectionQuery({ search: "chair", category: "living-room", availability: "available", sort: "price", sort_direction: "asc", page: "7" });

  assert.equal(productCollectionHref("/search", query, 2), "/search?search=chair&category=living-room&availability=available&sort=price&sort_direction=asc&page=2");
  assert.equal(productCollectionHref("/products", { ...query, page: "1" }), "/products?search=chair&category=living-room&availability=available&sort=price&sort_direction=asc");
});

test("supported sort pairs are explicit and stock_indicator is never serialized", () => {
  assert.equal(serializeProductCollectionQuery(parseProductCollectionQuery({ sort: "created_at", sort_direction: "desc" })), "");
  assert.equal(serializeProductCollectionQuery(parseProductCollectionQuery({ sort: "price", sort_direction: "desc" })), "sort=price&sort_direction=desc");
  assert.equal(serializeProductCollectionQuery(parseProductCollectionQuery({ stock_indicator: "LOW_STOCK" })), "");
});

test("category filter options use CAT-003 slugs and retrieve every API page without a large per_page override", async () => {
  await withApiDataSource(async () => {
    const calls: ApiRequestOptions[] = [];
    const options = await getCategoryFilterOptions(stubTransport((request) => {
      calls.push(request);
      const page = request.query && !(request.query instanceof URLSearchParams) ? request.query.page : undefined;
      return page === 2
        ? { data: [{ id: "cat_2", name: "Bedroom", slug: "bedroom", image: null }], meta: { pagination: { current_page: 2, per_page: 20, total: 2, last_page: 2, has_next: false, has_previous: true } } }
        : { data: [{ id: "cat_1", name: "Living Room", slug: "living-room", image: null }], meta: { pagination: { current_page: 1, per_page: 20, total: 2, last_page: 2, has_next: true, has_previous: false } } };
    }));

    assert.deepEqual(calls, [{ path: "/categories", cache: "no-store" }, { path: "/categories", cache: "no-store", query: { page: 2 } }]);
    assert.equal(serializeProductCollectionQuery({ category: options[0].slug }), "category=living-room");
  });
});

test("catalog controls preserve an existing search term independently of the destination pathname", () => {
  const controls = read("components/catalog/product-collection-controls.tsx");
  assert.match(controls, /\{search \? <input type="hidden" name="search" value=\{search\}/);
  assert.doesNotMatch(controls, /action === "\/search" && search/);
});
