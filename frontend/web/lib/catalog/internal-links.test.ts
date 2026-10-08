import assert from "node:assert/strict";
import test from "node:test";
import { createElement } from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { CatalogDiscovery } from "@/components/catalog/catalog-discovery";
import { ProductCard } from "@/components/catalog/product-card";
import { ProductCollectionPagination } from "@/components/catalog/product-collection-pagination";
import { ProductGrid } from "@/components/catalog/product-grid";
import { isSiteRouteImplemented } from "@/components/layout/site-navigation";
import { HOMEPAGE_CATEGORY_FIXTURES, HOMEPAGE_PRODUCT_FIXTURES } from "@/lib/homepage/fixtures";
import { read } from "../test-utils/frontend-test-helpers";

const NAVIGATION_SOURCES = [
  "components/layout/primary-category-navigation.tsx",
  "components/layout/mobile-navigation.tsx",
  "components/layout/site-footer.tsx",
  "components/layout/site-navigation.ts",
  "components/layout/search-affordance.tsx",
  "components/layout/brand-mark.tsx",
  "components/catalog/catalog-discovery.tsx",
  "components/catalog/product-card.tsx",
  "components/catalog/product-grid.tsx",
  "components/catalog/product-collection-pagination.tsx",
];

test("ProductGrid links to clean canonical PDPs using backend slugs and product names", () => {
  const markup = renderToStaticMarkup(createElement(ProductGrid, { products: HOMEPAGE_PRODUCT_FIXTURES, sizes: "33vw" }));

  for (const product of HOMEPAGE_PRODUCT_FIXTURES) {
    assert.match(markup, new RegExp(`href="/products/${product.slug}"`));
    assert.ok(markup.includes(product.name));
  }
  assert.doesNotMatch(markup, /href="\/products\/[^"]*\?/);
  assert.doesNotMatch(markup, /prod_/);
});

test("ProductCard exposes the product name as the canonical product link", () => {
  const product = HOMEPAGE_PRODUCT_FIXTURES[0];
  const markup = renderToStaticMarkup(createElement(ProductCard, { product, sizes: "33vw", href: `/products/${product.slug}` }));
  assert.match(markup, new RegExp(`<a[^>]*href="/products/${product.slug}"[^>]*>${product.name}</a>`));

  const withoutHref = renderToStaticMarkup(createElement(ProductCard, { product, sizes: "33vw" }));
  assert.doesNotMatch(withoutHref, /<a /);
});

test("homepage discovery links canonical category resources and exposes one /products action", () => {
  const markup = renderToStaticMarkup(createElement(CatalogDiscovery, {
    catalog: { source: "fixtures", categories: HOMEPAGE_CATEGORY_FIXTURES, products: HOMEPAGE_PRODUCT_FIXTURES },
  }));

  assert.match(markup, /href="\/categories\/living-room"/);
  assert.match(markup, /href="\/products"/);
  assert.match(markup, /View all furniture/);
  assert.doesNotMatch(markup, /href="\/products\?category=/);
});

test("collection and search pagination stay semantic and preserve only their discovery state", () => {
  const search = renderToStaticMarkup(createElement(ProductCollectionPagination, {
    pathname: "/search", ariaLabel: "Search result pages", query: { search: "chair", page: "2" }, currentPage: 2, lastPage: 3, hasPrevious: true, hasNext: true,
  }));
  assert.match(search, /href="\/search\?search=chair"/);
  assert.match(search, /href="\/search\?search=chair&amp;page=3"/);

  const filtered = renderToStaticMarkup(createElement(ProductCollectionPagination, {
    pathname: "/products", ariaLabel: "Product pages", query: { category: "living-room", page: "2" }, currentPage: 2, lastPage: 3, hasPrevious: true, hasNext: true,
  }));
  assert.match(filtered, /href="\/products\?category=living-room"/);
  assert.match(filtered, /href="\/products\?category=living-room&amp;page=3"/);
});

test("breadcrumbs are semantic, name the current page, and keep backend category slugs", () => {
  const categoryPage = read("app/categories/[slug]/page.tsx");
  const productPage = read("app/products/[slug]/page.tsx");
  const productsPage = read("app/products/page.tsx");

  assert.match(categoryPage, /aria-label="Breadcrumb"/);
  assert.match(categoryPage, /aria-current="page"/);
  assert.match(productPage, /href="\/products">Furniture/);
  assert.match(productPage, /href=\{`\/categories\/\$\{product\.category\.slug\}`\}/);
  assert.match(productPage, /aria-current="page"/);
  assert.match(productsPage, /aria-current="page"/);
});

test("global navigation keeps the seeded category destinations active and free of placeholder or programmatic navigation", () => {
  const combined = NAVIGATION_SOURCES.map((file) => read(file)).join("\n");

  assert.doesNotMatch(combined, /href="#"/);
  assert.doesNotMatch(combined, /router\.push|window\.location|location\.href/);
  assert.doesNotMatch(combined, /products\?category=/);
  assert.doesNotMatch(read("components/layout/primary-category-navigation.tsx"), /href=\{`\/categories\/\$\{category\.slug\}`\} inactive/);
  const mobileCategoryLink = read("components/layout/mobile-navigation.tsx").match(/<NavLink\s+href=\{`\/categories\/\$\{category\.slug\}`\}[\s\S]*?>/);
  assert.ok(mobileCategoryLink);
  assert.doesNotMatch(mobileCategoryLink[0], /\binactive\b/);
  assert.doesNotMatch(read("components/layout/site-footer.tsx"), /inactive: true/);
});

test("only implemented routes are activated while deferred storefront routes stay inactive", () => {
  assert.equal(isSiteRouteImplemented("/"), true);
  assert.equal(isSiteRouteImplemented("/products"), true);
  assert.equal(isSiteRouteImplemented("/search"), true);
  assert.equal(isSiteRouteImplemented("/categories/living-room"), true);
  assert.equal(isSiteRouteImplemented("/products/fixture-open-frame-armchair"), true);
  assert.equal(isSiteRouteImplemented("/furniture-requests"), true);
  assert.equal(isSiteRouteImplemented("/contact"), true);
  for (const reserved of ["/account", "/account/orders", "/cart", "/checkout"]) {
    assert.equal(isSiteRouteImplemented(reserved), false, `${reserved} must remain inactive`);
  }
});
