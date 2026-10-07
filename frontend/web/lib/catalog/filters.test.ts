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
import { read, stubTransport, withApiDataSource } from "../test-utils/frontend-test-helpers";
import { isInvertedRange, syncPriceField } from "@/components/catalog/price-filter-inputs";
import { SORT_OPTIONS, SortFilterSelect, toSortOptionValue } from "@/components/catalog/sort-filter-select";
import { createElement } from "react";
import { renderToStaticMarkup } from "react-dom/server";

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

test("price filter inputs convert whole TZS on change so the frozen minor-unit query is actually submitted", () => {
  const component = read("components/catalog/price-filter-inputs.tsx");
  assert.match(component, /onChange=\{syncPrices\}/);
  assert.doesNotMatch(component, /onSubmit/);

  const visible = { value: "200000", setCustomValidity: () => {} };
  const hidden = { name: "", value: "", dataset: { queryName: "max_price" } };
  assert.equal(syncPriceField(visible, hidden), "20000000");
  assert.equal(hidden.name, "max_price");
  assert.equal(hidden.value, "20000000");
});

test("price filter inputs omit invalid or empty values and flag inverted ranges", () => {
  const cleared = { name: "min_price", value: "100", dataset: { queryName: "min_price" } };
  assert.equal(syncPriceField({ value: "  ", setCustomValidity: () => {} }, cleared), "");
  assert.equal(cleared.name, "");
  assert.equal(cleared.value, "");

  const invalid = { name: "min_price", value: "100", dataset: { queryName: "min_price" } };
  assert.equal(syncPriceField({ value: "200000.5", setCustomValidity: () => {} }, invalid), "");
  assert.equal(invalid.name, "");

  assert.equal(isInvertedRange("500000", "200000"), true);
  assert.equal(isInvertedRange("200000", "500000"), false);
  assert.equal(isInvertedRange("200000", "200000"), false);
});

test("sort control maps every user option to one valid frozen sort pair", () => {
  assert.deepEqual(SORT_OPTIONS.map((option) => option.label), ["Newest", "Price: Low to high", "Price: High to low", "Name: A to Z", "Name: Z to A"]);
  assert.equal(toSortOptionValue("price", "asc"), "price:asc");
  assert.equal(toSortOptionValue("price", "desc"), "price:desc");
  assert.equal(toSortOptionValue("name", "asc"), "name:asc");
  assert.equal(toSortOptionValue("name", "desc"), "name:desc");
  assert.equal(toSortOptionValue("newest", ""), "");
  assert.equal(toSortOptionValue("created_at", "asc"), "");
  assert.equal(toSortOptionValue(undefined, undefined), "");
});

test("sort control submits the frozen pair through hidden inputs and omits it for Newest", () => {
  const asc = renderToStaticMarkup(createElement(SortFilterSelect, { sort: "price", sortDirection: "asc" }));
  assert.match(asc, /name="sort" value="price"/);
  assert.match(asc, /name="sort_direction" value="asc"/);
  assert.match(asc, /Price: Low to high/);

  const newest = renderToStaticMarkup(createElement(SortFilterSelect, { sort: "newest", sortDirection: "" }));
  assert.doesNotMatch(newest, /name="sort"/);
  assert.doesNotMatch(newest, /name="sort_direction"/);

  const controls = read("components/catalog/product-collection-controls.tsx");
  assert.match(controls, /SortFilterSelect/);
  assert.doesNotMatch(controls, /label="Order"/);
  assert.doesNotMatch(controls, /<FilterSelect label="Sort by"/);
});
